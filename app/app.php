<?php namespace APP;

use \CORE\Core;
use \BOOT\Log;

/**
 * App
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */
class App
{
	public static $instances = array(
			'unit'		=>array(),
			'model'		=>array(),
			'valid'		=>array()
	);

	/**
	 * load abstract classes
	 * @param string $abs unit|model|control
	 */
	public static function loadAbstract($abs)
	{
		foreach(array(_APPPATH, APPPATH, HOSTAPPPATH) as $path)
		{
			$file = $path.DIRECTORY_SEPARATOR.ucfirst($abs).'.php';
			if( \setRequire($file) === FALSE )
			{
				\BOOT\Log::w('ERROR', 'Do not found: '.$file);
			}
		}
	}

	/**
	 * singleton (unit, model)
	 *
	 * @param string $abs
	 * @param string $class
	 * @return boolean
	 */
	public static function &singleton($abs, $class)
	{
		$abs   = strtolower(trim($abs));
		$class = trim($class);

		if( in_array( $abs, array('unit', 'model', 'valid') ) === FALSE )
		{
			throw new \Exception('Not supported singleton type: '.$abs);
		}

		self::loadAbstract($abs);

		if( isset( self::$instances[$abs][$class] ) )
		{
			\BOOT\Log::w('INFO', 'Reuse '.$abs.': '.$class);
			return self::$instances[$abs][$class];
		}


		$tmp = explode('.', $class);

		$path = PROJECTPATH.DIRECTORY_SEPARATOR.$abs;
		$file = $path.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $tmp).'.'.$abs.'.php';


		$last = trim(array_pop($tmp));
		array_push($tmp, $last);
		$c = '\\PROJECT\\'.strtoupper($abs).'\\'.ucfirst($last);

		if( \setRequire( $file ) === FALSE )
		{
			// Refind class
			$file = $path.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $tmp).DIRECTORY_SEPARATOR.$last.'.'.$abs.'.php';

			if( \setRequire( $file ) === FALSE )
			{
				throw new \Exception('Cannot find singleton file: '.$file);
			}
		}

		if( class_exists( $c ) )
		{
			self::$instances[$abs][$class] = new $c($class);
			return self::$instances[$abs][$class];
		}

		throw new \Exception('Cannot create singleton object. Class not found: '.$c);
	}

	/**
	 * run control
	 */
	public static function run()
	{
		$router = Core::get('Router');
		$router->dispatch();

		// Get details from the router after dispatching
		$f = $router->getFile();
		$c = $router->getControl();
		$m = $router->getMethod();
		$params = $router->getParams();

		// If control is empty, it means a closure was handled or 404 was thrown.
		if (empty($c)) {
			return;
		}

		self::loadAbstract('control');

		if (!empty($f) && \setRequire($f) === FALSE) {
			throw new \Exception('Controller file not found: ' . $f);
		}
		if (!class_exists($c)) {
			throw new \Exception('Controller class not found: ' . $c);
		}

		if (!method_exists($c, $m)) {
			throw new \Exception('Method not found in controller: ' . $c . '::' . $m);
		}

		// Dependencies for the controller
		$input    = Core::get('Input');
		$security = Core::get('Security');
		$session  = Core::get('Session');
		$crypt    = Core::get('Crypt');

		$control = new $c($f, $c, $m, $router, $input, $security, $session, $crypt);

		// Call the controller method with route parameters
		call_user_func_array([$control, $m], $params);
	}

}

/* End of file app.php */
/* Location: ./app/app.php */