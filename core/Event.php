<?php

namespace CORE;

class Event
{
    /**
     * The Redis connection instance for publishing.
     * @var \Redis
     */
    protected static $redis;

    /**
     * Get the Redis connection.
     *
     * @return \Redis
     */
    protected static function redis()
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
     * @param string $channel The channel to publish to.
     * @param array  $data    The data to broadcast.
     * @return int The number of clients that received the message.
     */
    public static function publish($channel, array $data)
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