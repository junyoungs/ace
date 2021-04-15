<?php namespace CORE;
/**
 * Router
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\CORE
 *
 */

// ------------------------------------------------------------------------
class Router
{
	public $uri			= '';						// Uri
	public $path		= '/home';					// Path
	public $file		= '/home.control.php';		// File
	public $control		= 'home';					// Control
	public $method		= 'index';					// Method

	public $trace		= array();					// trace
	/**
	 * Constructor
	 *
	 * Runs the route mapping function.
	 */
	public function __construct()
	{
		// Run Routing
		$this->run();

		\BOOT\Log::w('INFO', '\\CORE\\Router class initialized.');
	}

	// --------------------------------------------------------------------

	/**
	 * Run Routing
	 *
	 * @access	public
	 * @return	void
	 */
	public function run()
	{
		$segments = explode('/', $this->__detect());

		// Prefix 처리
		if($segments[0] == \BOOT\Config::get('prefix'))
		{
		    array_shift($segments);
		    $segments = implode('/', $segments);
		    $segments = explode('/', $segments);
		}
		
		// uri 가 없을 경우
		if ( trim( $segments[0] ) == '' )
		{
			$this->setControl('home');
			$this->setMethod('index');
			$this->setPath(HOSTPATH.DIRECTORY_SEPARATOR.'control'.DIRECTORY_SEPARATOR.'home');
			$this->setFile($this->getPath().DIRECTORY_SEPARATOR.$this->getControl().'.control.php');
		}
		else
		{
    		if( ! $this->__setSegments($segments) )
    		{
    			if(MODE == 'development')
				{
					\BOOT\Log::w('ERROR', 'Not found router. ['.implode('>', $segments).']');
				}
				else
				{
					header('HTTP/1.1 404 Not Found');
					header("Location: /error404");
    				exit;
				}
    		}
    	}
	}

	// --------------------------------------------------------------------

	/**
	 * set segments
	 * @param array $segments
	 * @return boolean
	 */
	private function __setSegments(array $segments, $method='index')
	{
	    // uri를 Get parameter 로 인정하지 않음
		// exchange/kebhana/cny
		// array ( [0] => exchange [1] => kebhana [2] => cny )

		// Case 1. exchage/kebhana/cny/cny.control.php     > cny::index
		// Case 2. exchage/kebhana/cny.control.php         > cny::index
		// Case 3. exchage/kebhana/kebhana.control.php     > kebhana::cny
		// Case 4. exchage/kebhana.control.php             > kebhana::cny

	    // Case 1, 2
		// Set Default Method
	    $this->setMethod($method);

	    // Set Control Class
	    $this->setControl(array_pop($segments));

	    // Set Path & File
	    $this->setPath(HOSTPATH.DIRECTORY_SEPARATOR.'control'.(count($segments) > 0 ? DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments) : '').DIRECTORY_SEPARATOR.$this->getControl());
	    $this->setFile($this->getPath().DIRECTORY_SEPARATOR.$this->getControl().'.control.php');

	    // Check Path & File
	    $this->addTrace();
	    if(is_dir($this->getPath()) && file_exists($this->getFile())) return TRUE;

// 	    echo $this->getPath()."<br>";
// 	    echo $this->getFile()."<br>";
// 	    echo $this->getControl()."<br>";
// 	    echo $this->getMethod()."<br><br><br>";

	    $this->setPath(HOSTPATH.DIRECTORY_SEPARATOR.'control'.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments));
	    $this->setFile($this->getPath().DIRECTORY_SEPARATOR.$this->getControl().'.control.php');

	    // Check Path & File
	    $this->addTrace();
	    if(is_dir($this->getPath()) && file_exists($this->getFile())) return TRUE;

// 	    echo $this->getPath()."<br>";
// 	    echo $this->getFile()."<br>";
// 	    echo $this->getControl()."<br>";
// 	    echo $this->getMethod()."<br><br><br>";

	    // root	     
	    $this->setPath(HOSTPATH.DIRECTORY_SEPARATOR.'control');
	    $this->setFile($this->getPath().DIRECTORY_SEPARATOR.$this->getControl().'.control.php');

	    // Check Path & File
	    $this->addTrace();
	    if(is_dir($this->getPath()) && file_exists($this->getFile())) return TRUE;
	    
// 	    echo $this->getPath()."<br>";
// 	    echo $this->getFile()."<br>";
// 	    echo $this->getControl()."<br>";
// 	    echo $this->getMethod()."<br><br><br>";
	     
	    
		if(count($segments) > 0)
		{
			return $this->__setSegments($segments, $this->getControl());
		}

		return FALSE;
	}



	// --------------------------------------------------------------------

	/**
	 * detect uri
	 * @return string
	 */
	private function __detect()
	{
		if ( ! isset($_SERVER['REQUEST_URI']) || ! isset($_SERVER['SCRIPT_NAME']))
		{
			return '';
		}

		// uri
		$uri = $_SERVER['REQUEST_URI'];
		if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0)
		{
			$uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
		}
		else if (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0)
		{
			$uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
		}

		if (strncmp($uri, '?/', 2) === 0)
		{
			$uri = substr($uri, 2);
		}

		$tmp = array();
		foreach(explode('/', $uri) as $v)
		{
			$v = trim($v);
			if(strlen($v) > 0)
			{
				$tmp[] = $v;
			}
		}
		$parts = preg_split('#\?#i', implode('/', $tmp), 2);
		$uri = $parts[0];

		// modify _GET
		if ( array_key_exists(1, $parts) )
		{
			$_SERVER['QUERY_STRING'] = $parts[1];
			parse_str($_SERVER['QUERY_STRING'], $_GET);
		}
		else
		{
			$_SERVER['QUERY_STRING'] = '';
			$_GET = array();
		}

		$this->uri = trim(parse_url($uri, PHP_URL_PATH), '/');

		return $this->uri = $this->__filterUri($this->uri, TRUE);
	}



	// --------------------------------------------------------------------

	/**
	 * Filter segments for malicious characters
	 *
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	private function __filterUri($uri, $urlencode=TRUE)
	{
		$uri = preg_replace("|/*(.+?)/*$|", "\\1", $uri);

		// Convert programatic characters to entities
		$bad	= array('$',		'(',		')',		'%28',		'%29');
		$good	= array('&#36;',	'&#40;',	'&#41;',	'&#40;',	'&#41;');

		$uri = str_replace($bad, $good, $uri);

		return Core::get('Security')->removeInvisibleCharacters($uri, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * add trace
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	public function addTrace()
	{
		//if(count($this->trace) > 0 && $found)
		if(count($this->trace) > 0)
		{
			$tmp = array_pop($this->trace);
			$tmp['found'] = FALSE;
			$this->trace[] = $tmp;
		}

		$this->trace[] = array(
				'path' => $this->getPath(),
				'file' => $this->getFile(),
				'c'    => $this->getControl(),
				'm'    => $this->getMethod()
		);
	}


	// --------------------------------------------------------------------

	/**
	 * Set the control
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	public function setControl($control)
	{
		// Default : home
		if( in_array($control, array('', NULL)) )
		{
			$control = 'home';
		}
		
		$this->control = str_replace(array('/'), '', $control);

		// 앞 숫자 처리 2017.10.11
		if( preg_match('/[0-9]{1}/', substr($this->control, 0, 1)) )
		{
			$this->control = '_'.$this->control;
		}
	}

	public function getControl()
	{
	    // Class 명에 -가 포함되어서 요청이 올 경우 삭제 처리
		return str_replace('-', '', $this->control);
	}


	// --------------------------------------------------------------------

	/**
	 *  Set the method
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	public function setMethod($method)
	{
		if( in_array($method, ['', NULL]) )
		{
			$method = 'index';
		}
		$this->method = $method;
	}

	public function getMethod()
	{
		return $this->method;
	}


	// --------------------------------------------------------------------

	/**
	 *  Set the path
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	public function setPath($path)
	{
		$path = str_replace('//', '/', $path);
		$this->path = trim($path);
	}

	public function getPath()
	{
		return $this->path;
	}


	// --------------------------------------------------------------------

	/**
	 *  Set the file
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	public function setFile($file)
	{
		$file = str_replace('//', '/', $file);
		$this->file = trim($file);
	}

	public function getFile()
	{
		return $this->file;
	}

}
// END Router Class

/* End of file router.php */
/* Location: ./core/router.php */