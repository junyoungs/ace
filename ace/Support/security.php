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
 * Security Class
 *
 */
class Security
{
	/**
	 * Random Hash for protecting URLs
	 *
	 * @var string
	 * @access protected
	 */
	protected $_xssHash			= '';

	/**
	 * List of never allowed strings
	 *
	 * @var array
	 * @access protected
	 */
	protected $_never_allowed_str = array(
		'document.cookie'	=> '[removed]',
		'document.write'	=> '[removed]',
		'.parentNode'		=> '[removed]',
		'.innerHTML'		=> '[removed]',
		'window.location'	=> '[removed]',
		'-moz-binding'		=> '[removed]',
		'<!--'				=> '&lt;!--',
		'-->'				=> '--&gt;',
		'<![CDATA['			=> '&lt;![CDATA[',
		'<comment>'			=> '&lt;comment&gt;'
	);

	/* never allowed, regex replacement */
	/**
	 * List of never allowed regex replacement
	 *
	 * @var array
	 * @access protected
	 */
	protected $_never_allowed_regex = array(
		'javascript\s*:',
		'expression\s*(\(|&\#40;)', // CSS and IE
		'vbscript\s*:', // IE, surprise!
		'Redirect\s+302',
		"([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
	);

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		\BOOT\Log::w('INFO', '\\CORE\\Security class initialized.');
	}

	// --------------------------------------------------------------------

	/**
	 * XSS Clean
	 *
	 * Sanitizes data so that Cross Site Scripting Hacks can be
	 * prevented.  This function does a fair amount of work but
	 * it is extremely thorough, designed to prevent even the
	 * most obscure XSS attempts.  Nothing is ever 100% foolproof,
	 * of course, but I haven't been able to get anything passed
	 * the filter.
	 *
	 * Note: This function should only be used to deal with data
	 * upon submission.  It's not something that should
	 * be used for general runtime processing.
	 *
	 * This function was based in part on some code and ideas I
	 * got from Bitflux: http://channel.bitflux.ch/wiki/XSS_Prevention
	 *
	 * To help develop this script I used this great list of
	 * vulnerabilities along with a few other hacks I've
	 * harvested from examining vulnerabilities in other programs:
	 * http://ha.ckers.org/xss.html
	 *
	 * @param	mixed	string or array
	 * @param 	bool
	 * @return	string
	 */
	public function xssClean($str)
	{
		//Is the string an array?
		if (is_array($str))
		{
			foreach($str as $key => $value)
			{
				$str[$key] = $this->xssClean($value);
			}

			return $str;
		}

		// Remove Invisible Characters
		$str = $this->removeInvisibleCharacters($str);

		// Validate Entities in URLs
		$str = $this->_validateEntities($str);

		// URL Decode
		// 2014.10.01 불필요하다가 판단되는 부분 주석 처리, removeInvisibleCharacters 메소드에 주석 처리와 함께 같이 주석처리함.
		// $str = rawurldecode($str);

		// Convert character entities to ASCII
		$str = preg_replace_callback("/[a-z]+=([\'\"]).*?\\1/si", array($this, '_convertAttribute'), $str);
		$str = preg_replace_callback("/<\w+.*?(?=>|<|$)/si", array($this, '_decodeEntity'), $str);

		// Remove Invisible Characters Again!
		$str = $this->removeInvisibleCharacters($str);

		// Convert all tabs to spaces
		if (strpos($str, "\t") !== FALSE)
		{
			$str = str_replace("\t", ' ', $str);
		}

		// Capture converted string for later comparison
		$converted_string = $str;

		// Remove Strings that are never allowed
		$str = $this->_doNeverAllowed($str);

		// Makes PHP tags safe
		$str = str_replace(array('<?', '?'.'>'),  array('&lt;?', '?&gt;'), $str);

		// Compact any exploded words
		$words = array(
			'javascript', 'expression', 'vbscript', 'script', 'base64',
			'applet', 'alert', 'document', 'write', 'cookie', 'window'
		);

		foreach ($words as $word)
		{
			$temp = '';

			for ($i = 0, $wordlen = strlen($word); $i < $wordlen; $i++)
			{
				$temp .= substr($word, $i, 1)."\s*";
			}

			// We only want to do this when it is followed by a non-word character
			// That way valid stuff like "dealer to" does not become "dealerto"
			$str = preg_replace_callback('#('.substr($temp, 0, -3).')(\W)#is', array($this, '_compactExplodedWords'), $str);
		}

		/*
		 * Remove disallowed Javascript in links or img tags
		 * We used to do some version comparisons and use of stripos for PHP5,
		 * but it is dog slow compared to these simplified non-capturing
		 * preg_match(), especially if the pattern exists in the string
		 */
		do
		{
			$original = $str;

			if (preg_match("/<a/i", $str))
			{
				$str = preg_replace_callback("#<a\s+([^>]*?)(>|$)#si", array($this, '_jsLinkRemoval'), $str);
			}

			if (preg_match("/<img/i", $str))
			{
				$str = preg_replace_callback("#<img\s+([^>]*?)(\s?/?>|$)#si", array($this, '_jsImgRemoval'), $str);
			}

			if (preg_match("/script/i", $str) OR preg_match("/xss/i", $str))
			{
				$str = preg_replace("#<(/*)(script|xss)(.*?)\>#si", '[removed]', $str);
			}
		}
		while($original != $str);

		unset($original);

		// Remove evil attributes such as style, onclick and xmlns
		$str = $this->_removeEvilAttributes($str);

		// Sanitize naughty HTML elements
		$naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';
		$str = preg_replace_callback('#<(/*\s*)('.$naughty.')([^><]*)([><]*)#is', array($this, '_sanitizeNaughtyHtml'), $str);

		// Sanitize naughty scripting elements
		$str = preg_replace('#(alert|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si', "\\1\\2&#40;\\3&#41;", $str);


		// Final clean up
		// This adds a bit of extra precaution in case
		// something got through the above filters
		$str = $this->_doNeverAllowed($str);


		\BOOT\Log::w('INFO', 'XSS Filtering completed');
		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Random Hash for protecting URLs
	 *
	 * @return	string
	 */
	public function xssHash()
	{
		if ($this->_xssHash == '')
		{
			mt_srand();
			$this->_xssHash = md5(time() + mt_rand(0, 1999999999));
		}

		return $this->_xssHash;
	}

	// --------------------------------------------------------------------

	/**
	 * HTML Entities Decode
	 *
	 * This function is a replacement for html_entity_decode()
	 *
	 * The reason we are not using html_entity_decode() by itself is because
	 * while it is not technically correct to leave out the semicolon
	 * at the end of an entity most browsers will still interpret the entity
	 * correctly.  html_entity_decode() does not convert entities without
	 * semicolons, so we are left with our own little solution here. Bummer.
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function entityDecode($str, $charset='UTF-8')
	{
		if (stristr($str, '&') === FALSE)
		{
			return $str;
		}

		$str = html_entity_decode($str, ENT_COMPAT, $charset);
        // [Warning] preg_replace(): The /e modifier is no longer supported, use preg_replace_callback instead
		$str = preg_replace_callback('~&#x(0*[0-9a-f]{2,5})~i', function ($matches) { return chr(hexdec($matches[1])); }, $str);
		return preg_replace_callback('~&#([0-9]{2,4})~', function ($matches) { return chr($matches[1]); } , $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Filename Security
	 *
	 * @param	string
	 * @param 	bool
	 * @return	string
	 */
	public function sanitizeFilename($str, $relative_path = FALSE)
	{
		$bad = array(
			"../",
			"<!--",
			"-->",
			"<",
			">",
			"'",
			'"',
			'&',
			'$',
			'#',
			'{',
			'}',
			'[',
			']',
			'=',
			';',
			'?',
			"%20",
			"%22",
			"%3c",		// <
			"%253c",	// <
			"%3e",		// >
			"%0e",		// >
			"%28",		// (
			"%29",		// )
			"%2528",	// (
			"%26",		// &
			"%24",		// $
			"%3f",		// ?
			"%3b",		// ;
			"%3d"		// =
		);

		if ( ! $relative_path)
		{
			$bad[] = './';
			$bad[] = '/';
		}

		$str = $this->removeInvisibleCharacters($str, FALSE);
		return stripslashes(str_replace($bad, '', $str));
	}

	// ----------------------------------------------------------------

	/**
	 * Compact Exploded Words
	 *
	 * Callback function for xssClean() to remove whitespace from
	 * things like j a v a s c r i p t
	 *
	 * @param	type
	 * @return	type
	 */
	protected function _compactExplodedWords($matches)
	{
		return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
	}

	// --------------------------------------------------------------------

	/*
	 * Remove Evil HTML Attributes (like evenhandlers and style)
	 *
	 * It removes the evil attribute and either:
	 * 	- Everything up until a space
	 *		For example, everything between the pipes:
	 *		<a |style=document.write('hello');alert('world');| class=link>
	 * 	- Everything inside the quotes
	 *		For example, everything between the pipes:
	 *		<a |style="document.write('hello'); alert('world');"| class="link">
	 *
	 * @param string $str The string to check
	 * @return string The string with the evil attributes removed
	 */
	protected function _removeEvilAttributes($str)
	{
		// All javascript event handlers (e.g. onload, onclick, onmouseover), style, and xmlns
		$evil_attributes = array('on\w*', 'style', 'xmlns', 'formaction');


		do {
			$count = 0;
			$attribs = array();

			// find occurrences of illegal attribute strings with quotes (042 and 047 are octal quotes)
			preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is', $str, $matches, PREG_SET_ORDER);

			foreach ($matches as $attr)
			{
				$attribs[] = preg_quote($attr[0], '/');
			}

			// find occurrences of illegal attribute strings without quotes
			preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*([^\s>]*)/is', $str, $matches, PREG_SET_ORDER);

			foreach ($matches as $attr)
			{
				$attribs[] = preg_quote($attr[0], '/');
			}

			// replace illegal attribute strings that are inside an html tag
			if (count($attribs) > 0)
			{
				$str = preg_replace('/(<?)(\/?[^><]+?)([^A-Za-z<>\-])(.*?)('.implode('|', $attribs).')(.*?)([\s><]?)([><]*)/i', '$1$2 $4$6$7$8', $str, -1, $count);
			}

		} while ($count);

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Sanitize Naughty HTML
	 *
	 * Callback function for xssClean() to remove naughty HTML elements
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _sanitizeNaughtyHtml($matches)
	{
		// encode opening brace
		$str = '&lt;'.$matches[1].$matches[2].$matches[3];

		// encode captured opening or closing brace to prevent recursive vectors
		$str .= str_replace(array('>', '<'), array('&gt;', '&lt;'),
							$matches[4]);

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * JS Link Removal
	 *
	 * Callback function for xssClean() to sanitize links
	 * This limits the PCRE backtracks, making it more performance friendly
	 * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
	 * PHP 5.2+ on link-heavy strings
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _jsLinkRemoval($match)
	{
		return str_replace(
			$match[1],
			preg_replace(
				'#href=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si',
				'',
				$this->_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
			),
			$match[0]
		);
	}

	// --------------------------------------------------------------------

	/**
	 * JS Image Removal
	 *
	 * Callback function for xssClean() to sanitize image tags
	 * This limits the PCRE backtracks, making it more performance friendly
	 * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
	 * PHP 5.2+ on image tag heavy strings
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _jsImgRemoval($match)
	{
		return str_replace(
			$match[1],
			preg_replace(
				'#src=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
				'',
				$this->_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
			),
			$match[0]
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Attribute Conversion
	 *
	 * Used as a callback for XSS Clean
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _convertAttribute($match)
	{
		return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
	}

	// --------------------------------------------------------------------

	/**
	 * Filter Attributes
	 *
	 * Filters tag attributes for consistency and safety
	 *
	 * @param	string
	 * @return	string
	 */
	protected function _filter_attributes($str)
	{
		$out = '';

		if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches))
		{
			foreach ($matches[0] as $match)
			{
				$out .= preg_replace("#/\*.*?\*/#s", '', $match);
			}
		}

		return $out;
	}

	// --------------------------------------------------------------------

	/**
	 * HTML Entity Decode Callback
	 *
	 * Used as a callback for XSS Clean
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _decodeEntity($match)
	{
		return $this->entityDecode($match[0], strtoupper(\BOOT\Config::get('charset')));
	}

	// --------------------------------------------------------------------

	/**
	 * Validate URL entities
	 *
	 * Called by xssClean()
	 *
	 * @param 	string
	 * @return 	string
	 */
	protected function _validateEntities($str)
	{
		/*
		 * Protect GET variables in URLs
		 */

		 // 901119URL5918AMP18930PROTECT8198

		$str = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-]+)|i', $this->xssHash()."\\1=\\2", $str);

		/*
		 * Validate standard character entities
		 *
		 * Add a semicolon if missing.  We do this to enable
		 * the conversion of entities to ASCII later.
		 *
		 */
		// URL Decode가 이미 종료 되었고. 현재 입력된 데이터는 의도적으로 적은 데이터인데 형식만 보고 자동으로 entity로 판단 하는 부분은 문제가 있음.
		// 2014.10.01 불필요하다가 판단되는 부분 주석 처리
		// $str = preg_replace('#(&\#?[0-9a-z]{2,})([\x00-\x20])*;?#i', "\\1;\\2", $str);

		/*
		 * Validate UTF16 two byte encoding (x00)
		 *
		 * Just as above, adds a semicolon if missing.
		 *
		 */
		$str = preg_replace('#(&\#x?)([0-9A-F]+);?#i',"\\1\\2;",$str);

		/*
		 * Un-Protect GET variables in URLs
		 */
		$str = str_replace($this->xssHash(), '&', $str);

		return $str;
	}

	// ----------------------------------------------------------------------

	/**
	 * Do Never Allowed
	 *
	 * A utility function for xssClean()
	 *
	 * @param 	string
	 * @return 	string
	 */
	protected function _doNeverAllowed($str)
	{
		$str = str_replace(array_keys($this->_never_allowed_str), $this->_never_allowed_str, $str);

		foreach ($this->_never_allowed_regex as $regex)
		{
			$str = preg_replace('#'.$regex.'#is', '[removed]', $str);
		}

		return $str;
	}

	// ----------------------------------------------------------------------

	/**
	 * Remove Invisible Characters
	 *
	 * This prevents sandwiching null characters
	 * between ascii characters, like Java\0script.
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function removeInvisibleCharacters($str, $urlencoded = TRUE)
	{
		$nonDisplayables = array();

		// every control character except newline (dec 10)
		// carriage return (dec 13), and horizontal tab (dec 09)
		// 2014.10.01 불필요하다가 판단되는 부분 주석 처리, xssClean 메소드에 rawurldecode 주석 처리와 함께 같이 주석처리함.
		// if ($urlencoded)
		// {
		// 	$nonDisplayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
		// 	$nonDisplayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
		// }

		$nonDisplayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($nonDisplayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}
}

/* End of file security.php */
/* Location: ./core/security.php */
