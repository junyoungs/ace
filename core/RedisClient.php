<?php declare(strict_types=1);

namespace CORE;

use Redis;
use RedisException;
use Exception;

class RedisClient
{
    /**
     * @var array<string, Redis>
     */
    private static array $clients = [];

    /**
     * Get a Redis client instance.
     */
    public static function connection(string $name = 'default'): Redis
    {
        if (isset(self::$clients[$name])) {
            return self::$clients[$name];
        }

        if (!extension_loaded('redis')) {
            throw new Exception('PHP Redis extension is not installed.');
        }

        $configPath = PROJECTPATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
        if (!file_exists($configPath)) {
            throw new Exception('Database config file not found.');
        }
        $config = require $configPath;

        $redisConfig = $config['redis'][$name] ?? null;
        if (!$redisConfig) {
            throw new Exception("Redis connection '{$name}' not configured.");
        }

        $redis = new Redis();
        try {
            $redis->connect($redisConfig['host'], (int) $redisConfig['port']);
            if (!empty($redisConfig['password'])) {
                $redis->auth($redisConfig['password']);
            }
            $redis->select((int) $redisConfig['database']);
        } catch (RedisException $e) {
            throw new Exception('Redis connection failed: ' . $e->getMessage());
        }

        return self::$clients[$name] = $redis;
    }

    /**
     * Pass methods to the default Redis connection.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::connection()->{$method}(...$parameters);
    }
}