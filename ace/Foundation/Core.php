<?php declare(strict_types=1);

namespace ACE\Foundation;

use Exception;
use ACE\Database\Db;
use ACE\Http\Router;
use ACE\Support\Crypt;
use ACE\Support\Security;
use ACE\Support\Session;
use ACE\Support\Input;
use ACE\Support\Output;
use ACE\Support\Dev;

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
        $this->singleton(self::class, fn() => $this);
        $this->singleton(Db::class, fn() => new Db());
        $this->singleton(Router::class, fn() => new Router());
        $this->singleton(Security::class, fn() => new Security());
        $this->singleton(Crypt::class, fn() => new Crypt());
        $this->singleton(Input::class, fn() => new Input());
        $this->singleton(Output::class, fn() => new Output());
        $this->singleton(Session::class, fn() => new Session());

        if (defined('MODE') && MODE === 'development') {
			$this->singleton(Dev::class, fn() => new Dev());
		}
	}

    public function bind(string $id, callable $resolver): void { $this->bindings[$id] = $resolver; $this->isShared[$id] = false; }
    public function singleton(string $id, callable $resolver): void { $this->bindings[$id] = $resolver; $this->isShared[$id] = true; }

	public function get(string $id): ?object
	{
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (!isset($this->bindings[$id])) throw new Exception("Service not found: {$id}");

        $instance = ($this->bindings[$id])($this);
        if ($this->isShared[$id]) $this->instances[$id] = $instance;
		return $instance;
	}

    public function flushRequestState(): void
    {
        foreach ($this->isShared as $id => $isShared) {
            if (!$isShared) unset($this->instances[$id]);
        }
    }
}