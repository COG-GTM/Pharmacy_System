<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class PublishUserRegisteredEvent
{
    public function handle(UserRegistered $event)
    {
        try {
            $connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'rabbitmq'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest')
            );

            $channel = $connection->channel();
            $channel->exchange_declare('pharmacy_events', 'topic', false, true, false);

            $payload = json_encode([
                'event' => 'user.registered',
                'data' => [
                    'user_id' => $event->user->id,
                    'email' => $event->user->email,
                    'name' => $event->user->name,
                    'role' => $event->role,
                    'registered_at' => now()->toIso8601String(),
                ],
            ]);

            $msg = new AMQPMessage($payload, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $channel->basic_publish($msg, 'pharmacy_events', 'user.registered');

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            \Log::error('Failed to publish UserRegistered event: ' . $e->getMessage());
        }
    }
}
