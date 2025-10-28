<?php declare(strict_types=1);

namespace ACE\Database;

use Exception;


class Db
{
    private static array $instances = [];

	public function __construct()
	{
		// Db class initialized
	}

	private function buildConfigFor(string $driver, bool $isMaster): array
    {
        if ($driver === 'mysql') {
            $userKey = $isMaster ? 'DB_USERNAME' : 'DB_USERNAME_SLAVE';
            $passKey = $isMaster ? 'DB_PASSWORD' : 'DB_PASSWORD_SLAVE';
            $hostKey = $isMaster ? 'DB_HOST' : 'DB_HOST_SLAVE';
            $portKey = $isMaster ? 'DB_PORT' : 'DB_PORT_SLAVE';
            $dbKey   = $isMaster ? 'DB_DATABASE' : 'DB_DATABASE_SLAVE';

            return [
                'host' => env($hostKey, env('DB_HOST', '127.0.0.1')),
                'port' => env($portKey, env('DB_PORT', '3306')),
                'database' => env($dbKey, env('DB_DATABASE', 'ace_db')),
                'user' => env($userKey, env('DB_USERNAME', 'root')),
                'password' => env($passKey, env('DB_PASSWORD', '')),
            ];
        }

        if ($driver === 'sqlite') {
            return [
                'path' => env('DB_DATABASE_PATH', BASE_PATH . '/database/database.sqlite'),
            ];
        }

        throw new Exception("Database driver [{$driver}] is not supported.");
    }

	public function driver(string $driver, bool $isMaster = false): DatabaseDriverInterface
	{
        $driverKey = "{$driver}_" . ($isMaster ? 'master' : 'slave');

		if (isset(self::$instances[$driverKey])) {
			return self::$instances[$driverKey];
		}

        $driverName = ucfirst(strtolower($driver));
		$driverClass = "\\ACE\\Database\\{$driverName}Connector";

		if (!class_exists($driverClass)) {
            throw new Exception("Database driver class not found: {$driverClass}");
        }

        $connectionConfig = $this->buildConfigFor($driver, $isMaster);

        $instance = new $driverClass();
        $instance->connect($connectionConfig);

        self::$instances[$driverKey] = $instance;

		return $instance;
	}
}