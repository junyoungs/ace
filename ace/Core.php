<?php declare(strict_types=1);

namespace ACE;

use Exception;

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
        // ... (rest of the services)
	}
    // ... (rest of the class)
}