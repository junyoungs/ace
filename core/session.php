<?php

namespace CORE;

use \SessionHandlerInterface;
use \BOOT\Log;

/**
 * Redis-based Session Handler
 */
class RedisSessionHandler implements SessionHandlerInterface
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var string
     */
    private $prefix = 'framework_session:';

    public function __construct()
    {
        $this->redis = RedisClient::connection('session');
        // Get session lifetime from php.ini
        $this->ttl = (int) ini_get('session.gc_maxlifetime');
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($sessionId)
    {
        $data = $this->redis->get($this->prefix . $sessionId);
        return $data ?: '';
    }

    public function write($sessionId, $sessionData)
    {
        return $this->redis->setex($this->prefix . $sessionId, $this->ttl, $sessionData);
    }

    public function destroy($sessionId)
    {
        return $this->redis->del($this->prefix . $sessionId) > 0;
    }

    public function gc($maxLifetime)
    {
        // Redis handles expiration automatically, so this can be empty.
        return true;
    }
}


/**
 * Session Management Class
 *
 * This class now acts as a wrapper around PHP's native session handling,
 * which is configured to use Redis.
 */
class Session
{
    public function __construct()
    {
        // Set the custom session handler
        $handler = new RedisSessionHandler();
        session_set_save_handler($handler, true);

        // Start the session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        Log::w('INFO', '\\CORE\\Session class initialized with Redis handler.');
    }

    /**
     * Get a value from the session.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a value in the session.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all()
    {
        return $_SESSION;
    }

    /**
     * Check if a key exists in the session.
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a value from the session.
     *
     * @param string $key
     */
    public function del($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the entire session.
     */
    public function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}