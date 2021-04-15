<?php namespace CORE;
/**
 * Core
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\CORE
 */

/**
 * ------------------------------------------------------
 * include core files
 * ------------------------------------------------------
 */
class Core
{
	private static $__instance  = array();
	private static $__namespace = '\\CORE\\';

	public static function &get($name)
	{
		$name = trim($name);
		if ( array_key_exists($name, self::$__instance) )
		{
			return self::$__instance[$name];
		}

		\BOOT\Log::w('ERROR', 'Not found core: '.$name);

		return NULL;
	}

	public static function set($name)
	{
		$name = trim($name);
		if ( ! array_key_exists($name, self::$__instance) )
		{
			$class = self::$__namespace.$name;
			if( class_exists($class) )
			{
				self::$__instance[$name] = new $class;
			}
		}
	}
}

/**
 * Default Core Classes
 */
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'crypt.php' )	!== FALSE	?	Core::set('Crypt')	    : FALSE;
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'session.php' )	!== FALSE	?	Core::set('Session')	: FALSE;
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'security.php' )	!== FALSE	?	Core::set('Security')	: FALSE;
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'router.php' )	!== FALSE	?	Core::set('Router')		: FALSE;
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'input.php' )	!== FALSE	?	Core::set('Input')		: FALSE;
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'output.php' )	!== FALSE	?	Core::set('Output')		: FALSE;
\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'db.php' )		!== FALSE	?	Core::set('Db')			: FALSE;

/**
 * Development Core Classes
 */
if(MODE == 'development')
{
	\setRequire( _COREPATH.DIRECTORY_SEPARATOR.'dev.php' )	!== FALSE	?	Core::set('Dev')		: FALSE;
}




/* End of file core.php */
/* Location: ./core/core.php */