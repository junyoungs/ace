<?php declare(strict_types=1);

namespace ACE\Foundation;

use Exception;
use ACE\Db;
use ACE\Crypt;
use ACE\Session;
use ACE\Security;
use ACE\Router;
use ACE\Input;
use ACE\Output;
use ACE\Dev;

class Core
{
    private static ?self $instance = null;
	private array $bindings = [];
	private array $instances = [];
    private array $isShared = [];

	public function __construct()
	{
        self::$instance = $this;
		$this->registerCoreServices();
	}

    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            new self();
        }
        return self::$instance;
    }

	private function registerCoreServices(): void
	{
        $this->singleton('Core', fn() => $this);
        $this->singleton('Db', fn() => new Db());
        $this->singleton('Crypt', fn() => new Crypt());
        $this->singleton('Security', fn() => new Security());

        if (defined('MODE') && MODE === 'development') {
			$this->singleton('Dev', fn() => new Dev());
		}

        $this->bind('Router', fn() => new Router());
        $this->bind('Input', fn() => new Input());
        $this->bind('Output', fn() => new Output());
        $this->bind('Session', fn() => new Session());
	}

    public function bind(string $name, callable $resolver): void
    {
        $this->bindings[$name] = $resolver;
        $this->isShared[$name] = false;
    }

    public function singleton(string $name, callable $resolver): void
    {
        $this->bindings[$name] = $resolver;
        $this->isShared[$name] = true;
    }

	public function get(string $name): ?object
	{
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (!isset($this->bindings[$name])) {
            return null;
        }

        $resolver = $this->bindings[$name];
        $instance = $resolver($this);

        if ($this->isShared[$name]) {
            $this->instances[$name] = $instance;
        }

		return $instance;
	}

    public function flushRequestState(): void
    {
        foreach ($this->isShared as $name => $isShared) {
            if (!$isShared) {
                unset($this->instances[$name]);
            }
        }
    }
}