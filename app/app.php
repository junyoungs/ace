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
			$file = $path.DIRECTORY_SEPARATOR.$abs.'.php';
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
			\BOOT\Log::w('ERROR', 'Not supported: '.$abs.' > '.$class);
			return FALSE;
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
				\BOOT\Log::w('ERROR', 'Do not found file: '.$file);
				return FALSE;
			}
		}

		if( class_exists( $c ) )
		{
			self::$instances[$abs][$class] = new $c($class);
			return self::$instances[$abs][$class];
		}

		\BOOT\Log::w('ERROR', 'Do not create the object: '.$file);
		return FALSE;
	}

	/**
	 * run control
	 */
	public static function run()
	{
		self::loadAbstract('control');

		$f = Core::get('Router')->getFile();
		$c = Core::get('Router')->getControl();
		$m = Core::get('Router')->getMethod();

		if( \setRequire( $f ) === FALSE )
		{
			\BOOT\Log::w('ERROR', 'Do not found: File - '.$f);
		}
		if( ! class_exists( $c ) )
		{
			\BOOT\Log::w('ERROR', 'Do not found: Class - '.$c);
		}

		if( ! method_exists( $c, $m ) )
		{
			if( method_exists( $c, 'index' ) )
			{
				Core::get('Router')->setMethod('index');
				Core::get('Router')->addTrace();
				$m = Core::get('Router')->getMethod();
			}
			else
			{
				\BOOT\Log::w('ERROR', 'Do not found: Method - '.$c.'::'.$m);
			}
		}

		$control = new $c( $f, $c, $m );
		if( $c != $m )
		{
			$control->$m();
		}
		else
		{
			\BOOT\Log::w('ERROR', 'Not allowed same name - '.$c.'::'.$m);
		}
	}

}

/* End of file app.php */
/* Location: ./app/app.php */