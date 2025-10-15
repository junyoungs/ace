<?php namespace ACE\Support;
/**
 * Security
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\CORE
 */

// ------------------------------------------------------------------------

/**
 * Crypt Class
 *
 */
class Crypt
{
	var $key = '@#14';


	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
	    \BOOT\Log::w('INFO', '\\CORE\\Crypt class initialized.');
	}


	private function keyED($txt, $ekey)
	{
	    $ekey = hash('sha256', trim( (string)$ekey ));
		$ctr = 0;
		$tmp = "";
		for ($i=0, $c=strlen($txt); $i<$c; $i++)
		{
			if ( $ctr == strlen($ekey) ) $ctr = 0;

			$tmp .= substr($txt,$i,1) ^ substr($ekey, $ctr,1);
			$ctr++;
		}
		return $tmp;
	}

	public function en($txt, $key="")
	{
	    $key = trim( (string)$key );
		if( empty($key) ) $key = $this->key;

		$txt = trim( (string)$txt );

		srand( (double)microtime() * 1000000 );
		$ekey = hash('sha256',  rand(0, 32000) );

		$tcnt = strlen($txt);
		$ecnt = strlen($ekey);

		$tmp = '';
		for ($i=0, $j=0; $i<$tcnt; $i++)
		{
			if( $j == $ecnt ) $j = 0;

			$tmp .= substr($ekey, $j, 1) . (substr($txt, $i, 1) ^ substr($ekey, $j, 1));
			$j++;
		}
		return urlencode( base64_encode( $this->keyED($tmp, $key) ) );
	}

	public function de($txt, $key="")
	{
	    $key = trim( (string)$key );
		if( empty($key) ) $key = $this->key;

		$txt = $this->keyED( base64_decode( urldecode( trim( (string)$txt ) ) ), $key );

		$tmp = "";
		for ($i=0, $c=strlen($txt); $i<$c; $i++)
		{
			$hash = substr($txt,$i,1);
			$i++;
			$tmp.= (substr($txt,$i,1) ^ $hash);
		}
		return $tmp;
	}

	public function setKey($key)
	{
	    $key = trim( (string)$key );
		$this->key = $key;
	}

	public function getKey()
	{
		return $this->key;
	}
}

/* End of file crypt.php */
/* Location: ./core/crypt.php */
