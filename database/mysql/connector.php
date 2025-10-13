<?php
namespace DATABASE\MYSQL;

use \BOOT\Log;

class Connector
{
	public $db				= NULL;
	public $config			= NULL;
	public $conn 			= NULL;

	/**
	 * set config
	 */
	final private function __setConfig()
	{
		if( is_null( $this->config ) )
		{
			$config = \CORE\Core::get('Db')->getConfig('mysql');

			if( $this->db->isMaster )
			{
				$this->config = $config['master'][array_rand($config['master'], 1)];
			}
			else
			{
				$this->config = $config['slave'][array_rand($config['slave'], 1)];
			}
		}
	}

	final public function checkConnected()
	{
		if( is_null($this->conn) || ! $this->conn )
		{
			throw new \Exception('Connector::checkConnected Exception - Do not connected: Database > Mysql', 401);
		}
	}


	/**
	 * Connect
	 */
	final public function connect()
	{
		if( is_null( $this->conn ) )
		{
			$this->__setConfig();

			if( isset( $this->config['port'] ) )
			{
				$this->conn = mysqli_connect($this->config['host'], $this->config['user'], $this->config['password'], $this->config['database'], $this->config['port']);
			}
			else
			{
				$this->conn = mysqli_connect($this->config['host'], $this->config['user'], $this->config['password'], $this->config['database']);
			}

			if( ! $this->conn )
			{
				throw new \Exception('Connector::connect Exception - Do not connected: Database > Mysql', 400);
			}
			else
			{
				Log::w('INFO', 'Connected: Database > Mysql');
			}

			$this->query("set names utf8");
		}
		else
		{
			Log::w('INFO', 'Already connected: Database > Mysql');
		}
	}

	/**
	 * Close
	 */
	final public function close()
	{
		if( is_null($this->conn) || ! $this->conn )
		{
			Log::w('INFO', 'Already Disonnected: Database > Mysql');
		}
		else
		{
			$this->conn->close();
			$this->conn = NULL;
			Log::w('INFO', 'Disonnected: Database > Mysql');
		}
	}

	/**
	 * Reconnect
	 * @param String $sql
	 * @return Object
	 */
	final public function reconnect()
	{
		if ( mysqli_ping( $this->conn ) === FALSE )
		{
			$this->conn = NULL;
		}
	}

	/**
	 * excute Query
	 * @param String $sql
	 * @return Object
	 */
	final public function query($sql)
	{
		$this->checkConnected();

		if( $query = $this->conn->query($sql) )
		{
			return $query;
		}
		throw new \Exception('Connector::query Exception - ['.$this->errno().'] '.$this->error().' > '.$sql, 402);
	}

	/**
	 * Execute a prepared statement
	 * @param string $sql SQL query with placeholders (?)
	 * @param array $params Parameters to bind
	 * @return \mysqli_result|bool
	 */
	final public function prepareQuery($sql, $params = [])
	{
		$this->checkConnected();
		$stmt = $this->conn->prepare($sql);

		if ($stmt === false) {
			throw new \Exception('Prepare failed: (' . $this->conn->errno . ') ' . $this->conn->error . ' > ' . $sql);
		}

		if (!empty($params)) {
			$types = str_repeat('s', count($params)); // Treat all params as strings for simplicity
			$stmt->bind_param($types, ...$params);
		}

		if (!$stmt->execute()) {
			throw new \Exception('Execute failed: (' . $stmt->errno . ') ' . $stmt->error);
		}

		$result = $stmt->get_result();
		$stmt->close();

		return $result;
	}

	/**
	 * Escape
	 * @param String $sql
	 */
	final public function escape($v)
	{
		$this->checkConnected();
		return $this->conn->real_escape_string($v);
	}

	final public function escapeData(array $data)
	{
		$this->checkConnected();
		foreach ($data as $key => $val) { $data[$key] = $this->conn->real_escape_string($val); }
		return $data;
	}

	/**
	 * get error number
	 */
	final public function errno()
	{
		$this->checkConnected();
		return $this->conn->errno;
	}

	/**
	 * get error msg
	 */
	final public function error()
	{
		$this->checkConnected();
		return $this->conn->error;
	}

}