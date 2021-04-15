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

abstract class model
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

	final public function execute($sql)
	{
		return $this->db->sql->execute($sql);
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