<?php namespace APP;

use \CORE\Core;
use \BOOT\Log;

/**
 * Valid
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */

abstract class Valid
{
	public $class = NULL;

	public function __construct($class)
	{
		$this->class	= $class;

		//Public Core Classes
		$this->security = &Core::get('Security');
		$this->crypt    = &Core::get('Crypt');
	}

	/**
	 * Unit > Valid (accessible)
	 * @param string $valid
	 * @return object
	 */
	final public function &valid($valid)
	{
		return App::singleton('valid', $valid);
	}
}