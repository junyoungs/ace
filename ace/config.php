<?php namespace ACE;
/**
 * Config
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\BOOT
 */

class Config
{
	private static $__config = array();

	public static function get($key)
	{
		if ( array_key_exists($key, self::$__config) )
		{
			return self::$__config[$key];
		}
		return NULL;
	}

	public static function run($config)
	{
		self::$__config = $config;
	}
}
Config::run(\setRequire(PROJECTPATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.php'));


/* End of file config.php */
/* Location: ./boot/config.php */