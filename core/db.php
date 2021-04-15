<?php
namespace CORE;

/**
 * DB
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\CORE
 *
 */
class Db
{
	private $__supportedDriver	= array('mysql');
	private $__config			= NULL;

	public static $mysql		= NULL;
	public static $mongo		= NULL;
	public static $redis		= NULL;
	public static $memcache		= NULL;
	public static $apc			= NULL;

	public function __construct()
	{
		$this->__setConfig();

		\BOOT\Log::w('INFO', '\\CORE\\Db class initialized.');
	}


	/**
	 * set database config
	 */
	private function __setConfig()
	{
		if( is_null( $this->__config ) )
		{
			$this->__config = \setRequire( PROJECTPATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'database.php' );

			if( ! is_array($this->__config) )
			{
				\BOOT\Log::w('ERROR', 'Database Config does not exist.');
			}
		}
	}


	/**
	 * get config
	 * @param string $driver ('mysql', 'mongo', 'redis', 'memcache', 'apc'...)
	 * @return array
	 */
	public function getConfig( $driver )
	{
		$driver = $this->__checkDriver($driver);
		if( is_array($this->__config) && array_key_exists($driver, $this->__config) )
		{
			return $this->__config[$driver];
		}

		\BOOT\Log::w('ERROR', 'Config does not exist: Db > '.$driver);
	}

	/**
	 * set driver
	 *
	 * @param string $driver
	 * @return this
	 */
	public function &driver($driver, $isMaster=FALSE)
	{
		$driver = $this->__checkDriver($driver);

		if( ! is_null(self::$$driver) && self::$$driver->isMaster == TRUE )
		{
			\BOOT\Log::w('INFO', 'Reuse: Database Driver > '.$driver.'');
			return self::$$driver;
		}

		$path = WORKSPATH.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.$driver.DIRECTORY_SEPARATOR;

		if( \setRequire( $path.'connector.php' ) === FALSE )
		{
			\BOOT\Log::w('ERROR', 'Not found: Database > '.$driver.' > connector');
		}
		if( \setRequire( $path.'transaction.php' ) === FALSE )
		{
			\BOOT\Log::w('ERROR', 'Not found: Database > '.$driver.' > transaction');
		}
		if( \setRequire( $path.'sql.php' ) === FALSE )
		{
			\BOOT\Log::w('ERROR', 'Not found: Database > '.$driver.' > sql');
		}
		if( \setRequire( $path.'util.php' ) === FALSE )
		{
			\BOOT\Log::w('ERROR', 'Not found: Database > '.$driver.' > util');
		}

		$namespace		= '\\DATABASE\\'.strtoupper($driver).'\\';
		$connector		= $namespace.'Connector';
		$transaction	= $namespace.'Transaction';
		$sql			= $namespace.'Sql';
		$util			= $namespace.'Util';

		self::$$driver 					= new \stdClass();
		self::$$driver->isMaster		= $isMaster === TRUE ? TRUE : FALSE;

		self::$$driver->connector		= new $connector();
		self::$$driver->transaction		= new $transaction();
		self::$$driver->sql				= new $sql();
		self::$$driver->util			= new $util();

		self::$$driver->connector->db	= &self::$$driver;
		self::$$driver->transaction->db	= &self::$$driver;
		self::$$driver->sql->db			= &self::$$driver;
		self::$$driver->util->db		= &self::$$driver;

		self::$$driver->connector->connect();

		return self::$$driver;
	}

	/**
	 * get setting driver
	 *
	 * @param string $driver
	 * @return string
	 */
	public function &getDriver($driver) {
		$driver = $this->__checkDriver($driver);
		\BOOT\Log::w('INFO', 'Get Database Driver > '.$driver.'');
		return self::$$driver;
	}

	/**
	 * check supported driver
	 *
	 * @param string $driver
	 * @return string
	 */
	private function __checkDriver($driver)
	{
		$driver = strtolower(trim($driver));
		if( in_array($driver, $this->__supportedDriver) === FALSE )
		{
			\BOOT\Log::w('ERROR', 'Not supported: Database > '.$driver);
		}
		return $driver;
	}

}
