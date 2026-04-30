<?php

namespace App\Console;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumeEventsCommand extends Command
{
    protected $signature = 'events:consume';
    protected $description = 'Consume events from RabbitMQ and send notifications';

    public function handle()
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest')
        );

        $channel = $connection->channel();
        $channel->exchange_declare('pharmacy_events', 'topic', false, true, false);

        $channel->queue_declare('notification_queue', false, true, false, false);
        $channel->queue_bind('notification_queue', 'pharmacy_events', 'user.registered');
        $channel->queue_bind('notification_queue', 'pharmacy_events', 'order.created');
        $channel->queue_bind('notification_queue', 'pharmacy_events', 'order.confirmation_needed');

        $this->info('Notification service listening for events...');

        $channel->basic_consume('notification_queue', '', false, true, false, false, function ($msg) {
            $payload = json_decode($msg->body, true);
            $event = $payload['event'] ?? 'unknown';

            $this->info("Received event: {$event}");

            switch ($event) {
                case 'user.registered':
                    $this->handleUserRegistered($payload['data']);
                    break;
                case 'order.created':
                case 'order.confirmation_needed':
                    $this->handleOrderEvent($payload['data']);
                    break;
            }
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function handleUserRegistered(array $data)
    {
        $this->info("Sending welcome email to {$data['email']}");
        \Mail::raw(
            "Welcome to Pharmacy System, {$data['name']}!",
            function ($message) use ($data) {
                $message->to($data['email'])->subject('Welcome to Pharmacy System');
            }
        );
    }

    private function handleOrderEvent(array $data)
    {
        $this->info("Sending order notification for order #{$data['order_id']}");
    }
}
