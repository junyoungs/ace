<?php

namespace CORE;

use \Redis;

class RedisClient
{
    /**
     * @var array
     */
    private static $clients = [];

    /**
     * Get a Redis client instance.
     *
     * @param string $name The name of the redis connection from the config.
     * @return Redis
     * @throws \Exception
     */
    public static function connection($name = 'default')
    {
        if (isset(self::$clients[$name])) {
            return self::$clients[$name];
        }

        if (!extension_loaded('redis')) {
            throw new \Exception('PHP Redis extension is not installed.');
        }

        $configPath = PROJECTPATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
        if (!file_exists($configPath)) {
            throw new \Exception('Database config file not found.');
        }
        $config = require $configPath;

        $redisConfig = $config['redis'][$name] ?? null;
        if (!$redisConfig) {
            throw new \Exception("Redis connection '{$name}' not configured.");
        }

        $redis = new Redis();
        try {
            $redis->connect($redisConfig['host'], $redisConfig['port']);
            if (!empty($redisConfig['password'])) {
                $redis->auth($redisConfig['password']);
            }
            $redis->select($redisConfig['database']);
        } catch (\RedisException $e) {
            throw new \Exception('Redis connection failed: ' . $e->getMessage());
        }

        return self::$clients[$name] = $redis;
    }

    /**
     * Pass methods to the default Redis connection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return self::connection()->{$method}(...$parameters);
    }
}