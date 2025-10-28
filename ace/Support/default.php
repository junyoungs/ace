<?php
namespace ACE\Support;
/**
 * Functions
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\
 */

// -----------------------------------------------------------------------

/**
 * is STDIN
 */
function isCli()
{
	return (strtolower(php_sapi_name()) === 'cli' || defined('STDIN'));
}

// ------------------------------------------------------------------------

/**
 * require_once
 * @param string $path
 */
function setRequire($path)
{
	return file_exists($path) ? require_once($path) : FALSE;
}

// ------------------------------------------------------------------------

/**
 * make dir
 * @param string $dir
 */
function makeDir($dir)
{
	if (is_dir($dir)) return TRUE;
	if (!makeDir(dirname($dir))) return FALSE;
	return @mkdir($dir, 0755);
}

/**
 * Gets the value of an environment variable.
 *
 * @param  string  $key
 * @param  mixed   $default
 * @return mixed
 */
function env($key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

/**
 * Get the available container instance.
 *
 * @param  string|null  $abstract
 * @return mixed|\ACE\Foundation\Core
 */
function app($abstract = null)
{
    $container = \ACE\Foundation\Core::getInstance();

    if (is_null($abstract)) {
        return $container;
    }

    return $container->get($abstract);
}

/* End of file func.php */
/* Location: ./boot/func.php */