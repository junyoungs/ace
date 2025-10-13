<?php

namespace CORE;

class LockProvider
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
    protected $prefix = 'framework_lock:';

    public function __construct()
    {
        // Use the 'default' redis connection for locks
        $this->redis = RedisClient::connection('default');
    }

    /**
     * Attempt to acquire a lock.
     *
     * @param  string  $key The lock key.
     * @param  int  $ttl The lock lifetime in seconds.
     * @return bool True if the lock was acquired, false otherwise.
     */
    public function lock($key, $ttl = 10)
    {
        // The 'NX' option means "set if not exists". This is an atomic operation.
        return $this->redis->set($this->prefix . $key, 1, ['nx', 'ex' => $ttl]);
    }

    /**
     * Release a lock.
     *
     * @param  string  $key The lock key.
     * @return bool
     */
    public function release($key)
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    /**
     * Execute a callback within a lock.
     * Automatically acquires and releases the lock.
     *
     * @param string $key The lock key.
     * @param \Closure $callback The callback to execute.
     * @param int $ttl The lock lifetime.
     * @return mixed The result of the callback.
     * @throws \Exception If the lock cannot be acquired.
     */
    public function withLock($key, \Closure $callback, $ttl = 10)
    {
        if (!$this->lock($key, $ttl)) {
            throw new \Exception("Could not acquire lock for key: {$key}");
        }

        try {
            return $callback();
        } finally {
            $this->release($key);
        }
    }
}