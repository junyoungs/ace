<?php namespace APP;

use \CORE\Core;
use \BOOT\Log;

/**
 * Unit
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */

abstract class Unit
{
	public $class = NULL;

	//Public Core Classes
	public $security = NULL;

	public function __construct($class)
	{
		$this->class	= $class;

		//Public Core Classes
		// $this->router   = &Core::get('Router');            // 현재 사용하지는 마세요
		// $this->input    = &Core::get('Input');            // 현재 사용하지는 마세요
		$this->security = &Core::get('Security');
		$this->session  = &Core::get('Session');
		$this->crypt    = &Core::get('Crypt');
	}

	/**
	 * Unit > Unit (accessible)
	 * @param string $unit
	 * @return object
	 */
	final public function &unit($unit)
	{
		return App::singleton('unit', $unit);
	}

	/**
	 * Unit > Model (accessible)
	 * @param string $model
	 * @return object
	 */
	final public function &model($model)
	{
		return App::singleton('model', $model);
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