<?php

namespace CORE;

class Cache
{
    /**
     * The Redis connection instance.
     * @var \Redis
     */
    protected $redis;

    /**
     * The cache key prefix.
     * @var string
     */
    protected $prefix = 'framework_cache:';

    public function __construct()
    {
        // Use the 'cache' connection from the config
        $this->redis = RedisClient::connection('cache');
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value !== false ? unserialize($value) : $default;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int     $seconds
     * @return bool
     */
    public function set($key, $value, $seconds = 3600)
    {
        return $this->redis->setex(
            $this->prefix . $key,
            $seconds,
            serialize($value)
        );
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return bool
     */
    public function forever($key, $value)
    {
        return $this->redis->set(
            $this->prefix . $key,
            serialize($value)
        );
    }

    /**
     * Check if an item exists in the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key)
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    /**
     * Remove all items from the cache.
     * This is a dangerous operation, use with caution.
     *
     * @return bool
     */
    public function flush()
    {
        // In a real application, you might want a more sophisticated way
        // to flush only application-specific keys.
        return $this->redis->flushDB();
    }
}