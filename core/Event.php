<?php declare(strict_types=1);

namespace CORE;

use Redis;

class Event
{
    /**
     * The Redis connection instance for publishing.
     */
    protected static ?Redis $redis = null;

    /**
     * Get the Redis connection.
     */
    protected static function redis(): Redis
    {
        if (!static::$redis) {
            // Use the 'default' redis connection for broadcasting
            static::$redis = RedisClient::connection('default');
        }
        return static::$redis;
    }

    /**
     * Publish an event to a given channel.
     *
     * @return int|false The number of clients that received the message. False on error.
     */
    public static function publish(string $channel, array $data): int|false
    {
        // We'll wrap the data with some metadata
        $payload = json_encode([
            'event' => $channel,
            'data' => $data,
            'timestamp' => time(),
        ]);

        return static::redis()->publish($channel, $payload);
    }
}