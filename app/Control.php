<?php declare(strict_types=1);

namespace APP;

use \CORE\Core;
use \CORE\Router;
use \CORE\Input;
use \CORE\Security;
use \CORE\Session;
use \CORE\Crypt;
use \DATABASE\DatabaseDriverInterface;

/**
 * Control
 * Do not use singleton
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */
abstract class Control
{
	protected string $view = '';

	public function __construct(
		public readonly ?string $file,
		public readonly ?string $class,
		public readonly ?string $method,
		public readonly Router $router,
		public readonly Input $input,
		public readonly Security $security,
		public readonly Session $session,
		public readonly Crypt $crypt
	) {}

	final public function db(string $driver, bool $master = false): DatabaseDriverInterface
	{
		return Core::get('Db')->driver($driver, $master);
	}

	final public function getDbDriver(string $driver): ?DatabaseDriverInterface
	{
		return Core::get('Db')->getDriver($driver);
	}

	final public function cache(int $time = 0): self
	{
		if(MODE !== 'development')
		{
			Core::get('Output')->setCache($time);
		}

		return $this;
	}

	final public function setView(string $view): self
	{
		$this->view = $view;
		return $this;
	}

	final public function view(array $vars = []): self
	{
		Core::get('Output')->setView($this->view)->setAssign($vars)->draw();
		return $this;
	}

	final public function fetch(array $vars = []): string
	{
		return Core::get('Output')->setView($this->view)->setAssign($vars)->fetch();
	}

	final public function layout(?string $layout = null): self
	{
		Core::get('Output')->setLayout($layout);
		return $this;
	}
}