<?php namespace ACE\Support;
/**
 * Log
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\BOOT
 */

// ------------------------------------------------------------------------
class Log
{

	private static $__threshold		= 0;
	private static $__dateformat	= 'Y-m-d H:i:s';
	private static $__microtime		= 0;
	private static $__levels		= array('ERROR' => 1, 'WARNING' => 2, 'DEBUG' => 3,  'INFO' => 4, 'ALL' => 5);

	/**
	 * init
	 */
	public static function run()
	{
		self::$__threshold = (int)Config::get('log');
	}

	// --------------------------------------------------------------------

	/**
	 * Write Log File
	 *
	 * Generally this function will be called using the global log_message() function
	 *
	 * @param	string	the error level
	 * @param	string	the error message
	 * @return	bool
	 */
	public static function w($level='ERROR', $msg)
	{
		$level = strtoupper(trim($level));

		if ( ! isset(self::$__levels[$level]) || self::$__levels[$level] > self::$__threshold )
		{
			return FALSE;
		}

		if(MODE == 'development')
		{
			$path = _LOGPATH;
			$filepath = $path.DIRECTORY_SEPARATOR.'php-dev.log';
		}
		else
		{
			$path = _LOGPATH.DIRECTORY_SEPARATOR.date('Y'.DIRECTORY_SEPARATOR.'m'.DIRECTORY_SEPARATOR.'d');
			$filepath = $path.DIRECTORY_SEPARATOR.'php-'.date('H').'.log';
		}

		//make directories
		makeDir($path);

		//write log
		system('echo "['.PID.']'.$level.' - '.date(self::$__dateformat). ' - '.str_replace(array('"', "`", "'"), array('\"', "", "\'"), $msg.' - '.round(memory_get_usage(TRUE)/1024/1024, 2).'MB').'" >> '.$filepath);

		if($level == 'ERROR')
		{
		    if ( MODE == 'development'  )
		    {
    			echo $msg;
    			exit;
		    }
		}

		return TRUE;
	}

}
// END Log Class

// Run Log
Log::run();


/* End of file log.php */
/* Location: ./boot/log.php */