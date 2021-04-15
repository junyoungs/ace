<?php namespace BOOT;
/**
 * Boot
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\BOOT
 */


/**
 * Define Host, Tld, Mode
 */
$tmp = explode('.', strtolower($_SERVER['HTTP_HOST']));
defined('HOST') or define('HOST',	trim(@array_shift($tmp)));
define('TLD',	trim(@array_pop($tmp)));
unset($tmp);

if ( TLD == 'test' || TLD == 'dev' )
{
    define('MODE', 'development');
	error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}
else
{
	define('MODE', 'production');
	error_reporting(FALSE);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

/**
 * Set Path
 */
\setRequire( WORKSPATH.DIRECTORY_SEPARATOR.'boot'.DIRECTORY_SEPARATOR.'path.php' );

/**
 * Set Configure
 */
\setRequire( _BOOTPATH.DIRECTORY_SEPARATOR.'config.php' );

/**
 * Load Handler Class (exception, shutdown...)
 */
\setRequire( _BOOTPATH.DIRECTORY_SEPARATOR.'handler.php' );

/**
 * Load Log Class
 */
\setRequire( _BOOTPATH.DIRECTORY_SEPARATOR.'log.php' );




/* End of file boot.php */
/* Location: ./boot/boot.php */