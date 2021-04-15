<?php namespace BOOT;
/**
 * Path
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\BOOT
 */

/**
 * User Path
 */
define('APPPATH',           PROJECTPATH.DIRECTORY_SEPARATOR.'app');
define('UNITPATH',          PROJECTPATH.DIRECTORY_SEPARATOR.'unit');
define('VALIDPATH',         PROJECTPATH.DIRECTORY_SEPARATOR.'valid');
define('MODELPATH',         PROJECTPATH.DIRECTORY_SEPARATOR.'model');
define('VENDORPATH',        PROJECTPATH.DIRECTORY_SEPARATOR.'vendor');
define('HOSTPATH',          PROJECTPATH.DIRECTORY_SEPARATOR.'host'.DIRECTORY_SEPARATOR.HOST);
define('HOSTAPPPATH',       HOSTPATH.DIRECTORY_SEPARATOR.'app');

/**
 * Work Path
 */
define('_APPPATH',          WORKSPATH.DIRECTORY_SEPARATOR.'app');
define('_BOOTPATH',         WORKSPATH.DIRECTORY_SEPARATOR.'boot');
define('_COREPATH',         WORKSPATH.DIRECTORY_SEPARATOR.'core');
define('_DATABASEPATH',     WORKSPATH.DIRECTORY_SEPARATOR.'database');

define('_CACHEPATH',        CACHEPATH.DIRECTORY_SEPARATOR.HOST);

if(MODE == 'development')   define('_LOGPATH', LOGPATH.DIRECTORY_SEPARATOR.'dev');
else                        define('_LOGPATH', LOGPATH.DIRECTORY_SEPARATOR.HOST);

/* End of file path.php */
/* Location: ./boot/path.php */