<?php namespace CORE;
/**
 * Input
 * Pre-processes global input data for security
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\CORE
 *
 */

// ------------------------------------------------------------------------
class Input
{
	private		$_ip			= NULL;		// IP address of the current user (Int)ip2long

	protected	$_agent			= NULL;		// user agent (web browser) being used by the current user

	protected	$_get			= NULL;		// get
	protected	$_post			= NULL;		// post
	protected	$_request		= NULL;		// request (get + post)
	protected	$_files			= NULL;		// files
	protected	$_server		= NULL;		// server

//     protected	$_cookie		= NULL;		// cookie
//     protected	$_session		= NULL;		// session


	// --------------------------------------------------------------------
	public function __construct()
	{
		$this->__sanitizeGlobalData();		// Sanitize global arrays
		\BOOT\Log::w('INFO', '\\CORE\\Input class initialized.');
	}




	// --------------------------------------------------------------------
	/**
	 * Sanitize Globals
	 *
	 * @access	private
	 * @return	void
	 */
	private function __sanitizeGlobalData()
	{
		$this->__setVars($this->_get,		$_GET);				// Clean $_GET Data
		$this->__setVars($this->_post,		$_POST);			// Clean $_POST Data
		$this->__setVars($this->_request,	$_REQUEST);			// Clean $_REQUEST Data
		$this->__setVars($this->_files,		$_FILES);			// Clean $_FILES Data
		$this->__setVars($this->_server,	$_SERVER);			// Clean $_SERVER Data

//        $this->__setInnerVars($this->_cookie,	$_COOKIE);		// Clean $_COOKIE Data
//        $this->__setInnerVars($this->_session,	$_SESSION);		// Clean $_SESSION Data
	}





	// --------------------------------------------------------------------
	/**
	 * set inner vars
	 *
	 * @access	private
	 * @return	void
	 */
	private function __setVars( &$vars, &$values )
	{
		$vars   = new \stdClass();
		$values = (array)$values;

		foreach ($values as $k => $v)
		{
			$vars->$k = $this->__cleanInputData($v);
		}

		$values = NULL;
	}

	// --------------------------------------------------------------------
	/**
	 * set inner vars
	 *
	 * @access	private
	 * @return	void
	 */
	private function __setInnerVars( &$vars, &$values )
	{
		$vars   = new \stdClass();
		$values = (array)$values;

		foreach ($values as $k => $v)
		{
			$vars->$k = $this->__cleanInputData($v);
		}

		$values = NULL;
	}





	// --------------------------------------------------------------------
	/**
	* Clean Input Data
	*
	* This is a helper function. It escapes data and
	* standardizes newline characters to \n
	*
	* @access	private
	* @param	string
	* @return	string
	*/
	private function __cleanInputData($values)
	{
		// is array
		if (is_array($values))
		{
			$temp = array();
			foreach ($values as $k => $v)
			{
				$temp[$this->__cleanInputKeys($k)] = $this->__cleanInputData($v);
			}
			return $temp;
		}

		// Trim
		$values = trim($values);

		// Clean UTF-8
		if ($this->isAscii($values) === FALSE)
		{
			$values = @iconv('UTF-8', 'UTF-8//IGNORE', $values);
		}

		return $values;
	}






	// --------------------------------------------------------------------
	/**
	 * Clean Keys
	 *
	 * This is a helper function. To prevent malicious users
	 * from trying to exploit keys we make sure that keys are
	 * only named with alpha-numeric text and a few other items.
	 *
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	private function __cleanInputKeys($str)
	{
		$str = trim($str);
		if ( ! preg_match("/^[a-z0-9:_\/-]+$/i", $str))
		{
			\BOOT\Log::w('ERROR', '\\CORE\\Input - Disallowed Key Characters. ::'.$str);
		}

		// Clean UTF-8
		if ($this->isAscii($str) === FALSE)
		{
			$str = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
		}

		return $str;
	}


	// --------------------------------------------------------------------

	/**
	 * Is ASCII?
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function isAscii($str)
	{
		return (preg_match('/[^\x00-\x7F]/S', $str) == 0);
	}



	// --------------------------------------------------------------------

	/**
	 * Fetch
	 *
	 * This is a helper function to retrieve values from global arrays
	 *
	 * @access	private
	 * @param	array
	 * @param	string
	 * @param	bool
	 * @return	string
	 */
	private function __fetch(&$array, $index = '', $xss = FALSE)
	{
		if ( ! isset($array->$index) )
		{
			return NULL;
		}

		if ($xss === TRUE)
		{
			return Core::get('Security')->xssClean($array->$index);
		}

		return $array->$index;
	}

	// --------------------------------------------------------------------

	/**
	* Fetch an item from the _REQUEST array
	*
	* @access	public
	* @param	string
	* @param	bool
	* @return	string
	*/
	public function request($index=NULL, $xss=TRUE)
	{
		if($index == NULL)
		{
			$request = array();
			foreach($this->_request as $k => $v)
			{
				$request[$k] = $this->__fetch($this->_request, $k, $xss);
			}
			return $request;
		}
		return $this->__fetch($this->_request, $index, $xss);
	}


	// --------------------------------------------------------------------

	/**
	 * Fetch an item from the _GET array
	 *
	 * @access	public
	 * @param	string
	 * @param	bool
	 * @return	string
	 */
	public function get($index=NULL, $xss = TRUE)
	{
		if($index == NULL)
		{
			$get = array();
			foreach($this->_get as $k => $v)
			{
				$get[$k] = $this->__fetch($this->_get, $k, $xss);
			}
			return $get;
		}
		return $this->__fetch($this->_get, $index, $xss);
	}

	// --------------------------------------------------------------------

	/**
	* Fetch an item from the _POST array
	*
	* @access	public
	* @param	string
	* @param	bool
	* @return	string
	*/
	public function post($index=NULL, $xss = TRUE)
	{
		if($index == NULL)
		{
			$post = array();
			foreach($this->_post as $k => $v)
			{
				$post[$k] = $this->__fetch($this->_post, $k, $xss);
			}
			return $post;
		}
		return $this->__fetch($this->_post, $index, $xss);
	}


	// --------------------------------------------------------------------

	/**
	* Fetch an item from the _SERVER array
	*
	* @access	public
	* @param	string
	* @param	bool
	* @return	string
	*/
	public function server($index=NULL, $xss = FALSE)
	{
		if($index == NULL)
		{
			return $this->_server;
		}
		return $this->__fetch($this->_server, $index, $xss);
	}


	// --------------------------------------------------------------------

	/**
	* Fetch an item from the _FILES array
	*
	* @access	public
	* @param	string
	* @param	bool
	* @return	string
	*/
	public function files($index=NULL, $xss = FALSE)
	{
		if($index == NULL)
		{
			$files = array();
			foreach($this->_files as $k => $v)
			{
				$files[$k] = $this->__fetch($this->_files, $k, $xss);
			}
			return $files;
		}
		return $this->__fetch($this->_files, $index, FALSE);
	}

	// --------------------------------------------------------------------

	/**
	* Fetch the IP Address
	*
	* @return	string
	*/
	public function ip($long=TRUE)
	{
	    $proxy = \BOOT\Config::get('proxy');
		if ( $proxy )
		{
		    $HTTP_X_FORWARDED_FOR = explode(',', (string)$this->server('HTTP_X_FORWARDED_FOR'));

    	    foreach($HTTP_X_FORWARDED_FOR as $tmp)
    	    {
    	        $tmp = trim($tmp);
    	        if(strlen($tmp) > 5)
    	        {
    			    break;
    	        }
    	    }

            $this->_ip = $tmp;
		}
		else
		{
            $this->_ip =  $this->server('REMOTE_ADDR');
		}
		
		return $this->_ip = $long ? ip2long ($this->_ip) : $this->_ip;
	}

	// --------------------------------------------------------------------

	/**
	* Validate IP Address
	*
	* @access	public
	* @param	string
	* @param	string	ipv4 or ipv6
	* @return	bool
	*/
	public function validIp($ip, $which='')
	{
	    $ip = trim($ip);
	    if( (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) )
	    {
	        return $ip;
	    }
	    return '0.0.0.0';
	}


	// --------------------------------------------------------------------
	
	/**
	 * User Agent
	 *
	 * @access	public
	 * @return	string
	 */
	public function agent()
	{
	    if ( ! is_null($this->_agent) )
	    {
	        return $this->_agent;
	    }
	    return $this->_agent = $this->server('HTTP_USER_AGENT');
	}


	// --------------------------------------------------------------------
	
	/**
	 * country
	 *
	 * @access	public
	 * @return	array
	 */
	public function country()
	{
	    return [
	        'code' => $this->server('GEOIP_COUNTRY_CODE'),
	        'name' => $this->server('GEOIP_COUNTRY_NAME')
	    ];
	}
	

}

/* End of file input.php */
/* Location: ./framework/engine/input.php */