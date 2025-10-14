<?php namespace BOOT;
/**
 * Exceptions Class
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\
 *
 *
 *
 * 0000 - 0999 : \
 * 0100 - 0199 : \BOOT
 * 0200 - 0299 : \CORE
 * 0300 - 0399 : \APP
 * 0400 - 0899 : \DATABASE
 *     Connector    : 0500 - 0599
 *         0500 : Connector::connect
 *         0501 : Connector::checkConnected
 *         0502 : Connector::query
 *
 *     Transaction  : 0600 - 0699
 *         0600 : Transaction::__isMaster
 *         0601 : Transaction::__isTransaction
 *         0602 : Transaction::start
 *         0603 : Transaction::end
 *         0604 : Transaction::commit
 *         0605 : Transaction::rollback
 *
 *     Sql          : 0700 - 0799
 *         0700 : Sql::secureSql
 *         0701 : Sql::checkTable
 *         0702 : Sql::checkField
 *         0703 : Sql::checkValue
 *         0704 : Sql::checkData
 *         0705 : Sql::where
 *     Util         : 0800 - 0899
 *
 * 0900 - 0999 : \ETC
 *
 * 1000 - 1999 : Control
 * 2000 - 2999 : Unit
 * 3000 - 3999 : Valid
 * 4000 - 4999 : Model
 *
 * path.class.method-(001-999)::MSG
 *
 *
 */

// ------------------------------------------------------------------------
/**
 * Exception Interface
 *
 */
interface IfException
{
	/* Protected methods inherited from Exception class */
	public function getMessage();                 // Exception message
	public function getCode();                    // User-defined Exception code
	public function getFile();                    // Source filename
	public function getLine();                    // Source line
	public function getTrace();                   // An array of the backtrace()
	public function getTraceAsString();           // Formated string of trace

	/* Overrideable methods inherited from Exception class */
	public function __toString();                 // formated string for display
	//public function __toJsonString();             // formated string for display

	/* User methods */
	public function getDebug();                   // Debug...
	public function getCodeMessage();             // Exception error message (control)
}

// ------------------------------------------------------------------------
/**
 * \BOOT\Exception
 */
class Exception  extends \Exception implements IfException
{
    public function getCodeMessage()
    {
        $tmp            = explode( '::', (string)$this->getMessage() );
        $this->code     = strtolower( trim( (string)array_shift($tmp) ) );
        $this->message  = trim( (string)implode( '::', $tmp ) );

        return array($this->getCode(), $this->getMessage());
    }

	public function getDebug()
	{
		$debug = $this->getTrace();
		//$debug = array_shift($debug);

		$debug['args'] = NULL;
		if( ! empty($debug['args']) )
		{
			ob_start();
			var_dump($debug['args']);
			$debug['args'] = ob_get_contents();
			ob_end_clean();
		}
		return $debug;
	}

	public function __toString()
	{
		$error = array();

		$debug = $this->getDebug();

		array_push($error, '[CODE] '.$this->getCode());
		array_push($error, '[MESSAGE] '.$this->getMessage());

		if(array_key_exists('class', $debug))
		{
			array_push($error, '[CLASS] '.$debug['class']);
		}
		if(array_key_exists('function', $debug))
		{
			array_push($error, '[FUNCTION] '.$debug['function']);
		}
		if(array_key_exists('file', $debug))
		{
			array_push($error, '[FILE] '.$debug['file']);
		}
		if(array_key_exists('line', $debug))
		{
			array_push($error, '[LINE] '.$debug['line']);
		}

		array_push($error, '[ARGS] '. $debug['args']);
		array_push($error, '[TRACE] '.PHP_EOL. $this->getTraceAsString());

		return implode(PHP_EOL, $error) ;
	}


// 	public function __toJsonString()
// 	{
// 		$error = array();

// 		$debug = $this->getDebug();

// 		$error['code'] = $this->getCode();
// 		$error['message'] = $this->getMessage();

// 		if(array_key_exists('class', $debug))
// 		{
// 			$error['class'] = $debug['class'];
// 		}
// 		if(array_key_exists('function', $debug))
// 		{
// 			$error['function'] = $debug['function'];
// 		}
// 		if(array_key_exists('file', $debug))
// 		{
// 			$error['file'] = $debug['file'];
// 		}
// 		if(array_key_exists('line', $debug))
// 		{
// 			$error['line'] = $debug['line'];
// 		}

// 		$error['args']  = $debug['args'];
// 		$error['trace'] = $this->getTraceAsString();

// 		return json_encode($error);
// 	}
}
// END Exception Class





/**
 * error handler
 * @param unknown $severity
 * @param unknown $message
 * @param unknown $filename
 * @param unknown $line
 */
function handler(int $severity, string $message, string $filename, int $line): void
{
    $errors = match ($severity) {
        E_NOTICE, E_USER_NOTICE => "Notice",
        E_WARNING, E_USER_WARNING => "Warning",
        E_ERROR, E_USER_ERROR => "Fatal Error",
        default => "Unknown Error",
    };

    if (MODE === 'production') {
        error_log(sprintf("PHP %s:  %s in %s on line %d", $errors, $message, $filename, $line));
    }
    else
    {
        echo "[".$errors."] ".$message."<br />".$filename." on line <b>".$line."</b><br />";
        exit(1);
    }

}
set_error_handler("\BOOT\handler");


/**
 * Uncaught Exception Handler
 * @param \Exception $exception
 */
function exception_handler($exception)
{
    // 500 Internal Server Error
    http_response_code(500);

    if (MODE === 'development') {
        echo "<h1>Uncaught Exception</h1>";
        echo "<p><b>Message:</b> " . $exception->getMessage() . "</p>";
        echo "<p><b>File:</b> " . $exception->getFile() . "</p>";
        echo "<p><b>Line:</b> " . $exception->getLine() . "</p>";
        echo "<h2>Stack Trace</h2><pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        // Log the full error
        error_log($exception->__toString());
        // Show a generic error message to the user
        echo "<h1>500 Internal Server Error</h1>";
        echo "<p>A critical error occurred. Please try again later.</p>";
    }
    exit(1);
}

set_exception_handler('\BOOT\exception_handler');


/* End of file handler.php */
/* Location: ./boot/handler.php */