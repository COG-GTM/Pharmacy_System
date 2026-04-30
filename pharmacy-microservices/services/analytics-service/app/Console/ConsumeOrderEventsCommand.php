<?php

namespace App\Console;

use App\Models\OrderStats;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumeOrderEventsCommand extends Command
{
    protected $signature = 'events:consume-orders';
    protected $description = 'Consume order events from RabbitMQ for analytics';

    public function handle()
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'), 5672, 'guest', 'guest'
        );

        $channel = $connection->channel();
        $channel->exchange_declare('pharmacy_events', 'topic', false, true, false);
        $channel->queue_declare('analytics_queue', false, true, false, false);
        $channel->queue_bind('analytics_queue', 'pharmacy_events', 'order.delivered');
        $channel->queue_bind('analytics_queue', 'pharmacy_events', 'order.status_changed');

        $this->info('Analytics service listening for order events...');

        $channel->basic_consume('analytics_queue', '', false, true, false, false, function ($msg) {
            $payload = json_decode($msg->body, true);
            $event = $payload['event'] ?? 'unknown';
            $data = $payload['data'] ?? [];

            $this->info("Received event: {$event}");

            switch ($event) {
                case 'order.delivered':
                    OrderStats::updateOrCreate(
                        ['order_id' => $data['order_id']],
                        [
                            'pharmacy_id' => $data['pharmacy_id'],
                            'status' => 'Delivered',
                            'price' => $data['price'],
                            'delivered_at' => $data['delivered_at'],
                        ]
                    );
                    break;

                case 'order.status_changed':
                    OrderStats::updateOrCreate(
                        ['order_id' => $data['order_id']],
                        ['status' => $data['new_status']]
                    );
                    break;
            }
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }
}
