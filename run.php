<?php
/**
 * PHP Framework
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\
 */

/*
 * ------------------------------------------------------
 *  Define the Version & PID
 * ------------------------------------------------------
 */
	define('VERSION', '1.0.0');
	define('PID', uniqid());

/*
 * ------------------------------------------------------
 *  Function
 * ------------------------------------------------------
 */
	require_once WORKSPATH.DIRECTORY_SEPARATOR.'func'.DIRECTORY_SEPARATOR.'default.php';

/*
 * ------------------------------------------------------
 *  Boot
 * ------------------------------------------------------
 */
	\setRequire( WORKSPATH.DIRECTORY_SEPARATOR.'boot'.DIRECTORY_SEPARATOR.'boot.php' );
	\setRequire( WORKSPATH.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'Route.php' );
	\setRequire( WORKSPATH.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'QueryBuilder.php' );
	\BOOT\Log::w('INFO', 'Boot Done.');

/*
 * ------------------------------------------------------
 *  Core
 * ------------------------------------------------------
 */

	\setRequire( WORKSPATH.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'core.php' );
	\BOOT\Log::w('INFO', 'Core Done.');

/*
 * ------------------------------------------------------
 *  App
 * ------------------------------------------------------
 */
	\setRequire( WORKSPATH.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'app.php' );
	\BOOT\Log::w('INFO', 'App Ready.');

/*
 * ------------------------------------------------------
 *  Run
 * ------------------------------------------------------
 */
	\APP\App::run();
	\BOOT\Log::w('INFO', 'App Done.');

/* End of file run.php */