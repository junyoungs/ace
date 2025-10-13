<?php namespace APP;

use \CORE\Core;
use \BOOT\Log;

/**
 * Control
 * Do not use singleton
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */
abstract class Control
{
	public $file = NULL;
	public $class = NULL;
	public $method = NULL;

	//Public Core Classes
	public $router;
	public $input;
	public $security;
	public $session;
	public $crypt;

	protected $view = '';

	function __construct($file, $class, $method, $router, $input, $security, $session, $crypt)
	{
		$this->file		= $file;
		$this->class	= $class;
		$this->method	= $method;

		// Injected Core Classes
		$this->router   = $router;
		$this->input    = $input;
		$this->security = $security;
		$this->session  = $session;
		$this->crypt    = $crypt;
	}

	final public function &db($driver, $master=FALSE)
	{
		Log::w('INFO', 'Database Driver: '.strtoupper($driver).' > '.($master ? 'Master':'Slave'));
		return Core::get('Db')->driver($driver, $master);
	}

	final public function &getDbDriver($driver)
	{
		$__driver = &Core::get('Db')->getDriver($driver);
		return $__driver;
	}

	final public function &unit($unit)
	{
		return App::singleton('unit', $unit);
	}

	final public function &model($model)
	{
		return App::singleton('model', $model);
	}

	final public function &valid($valid)
	{
		return App::singleton('valid', $valid);
	}

	final public function cache($time=0)
	{
		if(MODE !== 'development')
		{
			Core::get('Output')->setCache($time);
		}
		
		return $this;
	}

	final public function setView($view)
	{
		$this->view = $view;
		return $this;
	}

	final public function view($vars=array())
	{
		Core::get('Output')->setView($this->view)->setAssign($vars)->draw();
		return $this;
	}

	final public function fetch($vars=array())
	{
		return Core::get('Output')->setView($this->view)->setAssign($vars)->fetch();
	}

	final public function layout($layout=NULL)
	{
		Core::get('Output')->setLayout($layout);
		return $this;
	}
}