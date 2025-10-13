<?php declare(strict_types=1);

namespace CORE;

use \BOOT\Log;
use \DATABASE\DatabaseDriverInterface;
use \Exception;
use \Closure;

/**
 * DB Manager
 *
 * @author		Junyoung Park (Original), Jules (Refactor)
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		2.0.0
 * @namespace	\CORE
 */
class Db
{
    /** @var array<string, DatabaseDriverInterface> */
    private static array $instances = [];
	private ?array $config = null;

	public function __construct()
	{
		$this->setConfig();
		Log::w('INFO', '\\CORE\\Db class initialized.');
	}

	private function setConfig(): void
	{
		if (is_null($this->config)) {
			$this->config = require(PROJECTPATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php');
			if (!is_array($this->config)) {
				throw new Exception('Database config file not found or invalid.');
			}
		}
	}

	public function getConfigFor(string $driver, bool $isMaster): array
    {
        $connectionConfig = $this->config['connections'][$driver] ?? null;
        if (!$connectionConfig) {
            throw new Exception("Config not found for database driver: {$driver}");
        }

        $serverType = $isMaster ? 'master' : 'slave';
        $servers = $connectionConfig[$serverType] ?? [];
        if (empty($servers)) {
            // Fallback to master if slave is not defined
            $servers = $connectionConfig['master'] ?? [];
            if (empty($servers)) {
                throw new Exception("No '{$serverType}' or 'master' servers configured for driver: {$driver}");
            }
        }

        // Return a random server from the list
        return is_array(reset($servers)) ? $servers[array_rand($servers)] : $servers;
    }

	/**
	 * Get a database driver instance.
	 */
	public function driver(string $driver, bool $isMaster = FALSE): DatabaseDriverInterface
	{
        $driverKey = "{$driver}_" . ($isMaster ? 'master' : 'slave');

		if (isset(self::$instances[$driverKey])) {
            Log::w('INFO', "Reuse: Database Driver > {$driverKey}");
			return self::$instances[$driverKey];
		}

        $driverName = ucfirst(strtolower($driver)); // mysql -> Mysql
		$driverClass = "\\DATABASE\\{$driverName}\\{$driverName}Connector";
        $driverFile = WORKSPATH . "/database/{$driver}/{$driverName}Connector.php";

        if (!file_exists($driverFile)) {
            throw new Exception("Database driver file not found: {$driverFile}");
        }
        require_once $driverFile;

		if (!class_exists($driverClass)) {
            throw new Exception("Database driver class not found: {$driverClass}");
        }

        $connectionConfig = $this->getConfigFor($driver, $isMaster);

        $instance = new $driverClass();
        $instance->connect($connectionConfig);

        self::$instances[$driverKey] = $instance;
        Log::w('INFO', "Created: Database Driver > {$driverKey}");

		return $instance;
	}

    /**
     * @deprecated The query builder should be used instead of accessing the db object directly.
     */
	public function getDriver(string $driver): DatabaseDriverInterface {
		// This method is largely obsolete with the new architecture.
        // It might be needed for the Model's __setDb method for now.
        $driverKey = "{$driver}_slave"; // Assume slave for legacy calls
        if (isset(self::$instances[$driverKey])) {
            return self::$instances[$driverKey];
        }
        // Fallback to creating a new slave connection
        return $this->driver($driver, false);
	}

    /**
     * Execute a database transaction.
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, string $connectionName = 'mysql'): mixed
    {
        $db = $this->driver($connectionName, true); // Transactions must use master

        $db->beginTransaction();

        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e; // Re-throw the exception after rolling back
        }
    }
}