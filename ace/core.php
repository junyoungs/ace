<?php declare(strict_types=1);

namespace ACE;

use Exception;

/**
 * Core Service Container
 */
class Core
{
	/** @var array<string, callable|object> */
	private array $bindings = [];

    /** @var array<string, object> */
	private array $instances = [];

    /** @var array<string, bool> */
    private array $isShared = [];

	public function __construct()
	{
		$this->registerCoreServices();
	}

	private function registerCoreServices(): void
	{
		// Shared services (singletons)
        $this->singleton('Db', fn() => new Db());
        $this->singleton('Crypt', fn() => new Crypt());
        $this->singleton('Security', fn() => new Security());

        if (defined('MODE') && MODE === 'development') {
			$this->singleton('Dev', fn() => new Dev());
		}

        // Services created new for each request
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
        // If it's a shared instance and already exists, return it.
        if (isset($this->instances[$name]) && $this->isShared[$name]) {
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

    /**
     * Clear all non-shared instances, to be called between requests.
     */
    public function flushRequestState(): void
    {
        foreach ($this->isShared as $name => $isShared) {
            if (!$isShared) {
                unset($this->instances[$name]);
            }
        }
    }
}