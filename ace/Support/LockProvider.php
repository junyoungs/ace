<?php declare(strict_types=1);

namespace ACE\Support;

use Redis;
use Closure;
use Exception;

class LockProvider
{
    /**
     * The Redis connection instance.
     */
    protected Redis $redis;

    /**
     * The cache key prefix.
     */
    protected string $prefix = 'ace_lock:';

    public function __construct()
    {
        // Use the 'default' redis connection for locks
        $this->redis = RedisClient::connection('default');
    }

    /**
     * Attempt to acquire a lock.
     */
    public function lock(string $key, int $ttl = 10): bool
    {
        // The 'NX' option means "set if not exists". This is an atomic operation.
        return $this->redis->set($this->prefix . $key, 1, ['nx', 'ex' => $ttl]);
    }

    /**
     * Release a lock.
     */
    public function release(string $key): bool
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    /**
     * Execute a callback within a lock.
     * Automatically acquires and releases the lock.
     * @throws \Exception If the lock cannot be acquired.
     */
    public function withLock(string $key, Closure $callback, int $ttl = 10): mixed
    {
        if (!$this->lock($key, $ttl)) {
            throw new Exception("Could not acquire lock for key: {$key}");
        }

        try {
            return $callback();
        } finally {
            $this->release($key);
        }
    }
}