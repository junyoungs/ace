<?php declare(strict_types=1);

namespace ACE\Support;

use Redis;

class Cache
{
    /**
     * The Redis connection instance.
     */
    protected Redis $redis;

    /**
     * The cache key prefix.
     */
    protected string $prefix = 'ace_cache:';

    public function __construct()
    {
        // Use the 'cache' connection from the config
        $this->redis = RedisClient::connection('cache');
    }

    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value !== false ? unserialize($value) : $default;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function set(string $key, mixed $value, int $seconds = 3600): bool
    {
        return $this->redis->setex(
            $this->prefix . $key,
            $seconds,
            serialize($value)
        );
    }

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->redis->set(
            $this->prefix . $key,
            serialize($value)
        );
    }

    /**
     * Check if an item exists in the cache.
     */
    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    /**
     * Remove all items from the cache.
     * This is a dangerous operation, use with caution.
     */
    public function flush(): bool
    {
        // In a real application, you might want a more sophisticated way
        // to flush only application-specific keys.
        return $this->redis->flushDB();
    }
}