<?php namespace APP;

use \CORE\Core;
use \BOOT\Log;

/**
 * Model
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */

abstract class Model
{
	public	$db		= NULL;
	public	$class	= NULL;
	public	$driver	= NULL;

	//Public Core Classes
	public $security = NULL;

	public function __construct($class)
	{
		$this->class	= $class;

		//Public Core Classes
		$this->security = &Core::get('Security');
		$this->crypt    = &Core::get('Crypt');

 		$this->__setDb();
	}

	final private function __setDb()
	{
 		$tmp = explode('.', $this->class);
 		$this->driver = strtolower(trim((string)array_shift($tmp)));

		$this->db = &Core::get('Db')->driver($this->driver);      // connect slave database
		Log::w('INFO', 'Database Driver: '.$this->driver.':'.($this->db->isMaster ? 'master':'slave'));
	}

	final public function comment()
	{
		$trace = debug_backtrace();
		$call = array_shift($trace);
		$file = explode('/',$call['file']);
		$file = array_pop($file);
		$line = $call['line'];
		return ' /* '.$file.' >> Line '.$line.' */ ';
	}

	/**
	 * Executes a prepared SQL query.
	 *
	 * @param string $sql The SQL query with placeholders (?).
	 * @param array $params The parameters to bind to the query.
	 * @return \mysqli_result|bool The result of the query.
	 */
	final public function query($sql, $params = [])
	{
		return $this->db->sql->query($sql, $params);
	}

	/**
	 * @deprecated This method is vulnerable to SQL injection. Use query() with prepared statements instead.
	 */
	final public function execute($sql)
	{
		throw new \Exception("execute() is deprecated due to security risks. Use query() with prepared statements.");
	}

	/**
	 * Unit > Valid (accessible)
	 * @param string $valid
	 * @return object
	 */
	final public function &valid($valid)
	{
		return App::singleton('valid', $valid);
	}
}