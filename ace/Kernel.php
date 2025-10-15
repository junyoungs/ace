<?php declare(strict_types=1);

namespace ACE;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response as Psr7Response;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

class Kernel
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

	public static function run(): void
	{
        $kernel = self::getInstance();
        $request = ServerRequestFactory::fromGlobals();
        $response = $kernel->handle($request);
        (new SapiEmitter())->emit($response);
	}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->flushRequestState();
        $this->singleton(ServerRequestInterface::class, fn() => $request);

        try {
            /** @var Router $router */
            $router = $this->get(Router::class);
            $route = $router->dispatch($request);

            /** @var Control $controller */
            $controller = $this->get($route['class']);
            $responsePayload = call_user_func_array([$controller, $route['method']], $route['params']);

            return $this->createResponse($responsePayload, $request->getMethod());

        } catch (\Throwable $e) {
            $statusCode = $e->getCode() >= 400 ? $e->getCode() : 500;
            return new Psr7Response(json_encode(['error' => $e->getMessage()]), $statusCode, ['Content-Type' => 'application/json']);
        }
    }

	private function createResponse(mixed $payload, string $httpMethod): ResponseInterface
    {
        $response = new Psr7Response();
        if ($payload === null) return $response->withStatus(204);
		if (is_array($payload) || is_object($payload)) {
			$response->getBody()->write(json_encode($payload));
            $response = $response->withHeader('Content-Type', 'application/json');
			$statusCode = $response->getStatusCode();
			if ($statusCode < 300) {
				$method = $httpMethod === 'POST' ? 201 : 200;
                return $response->withStatus($method);
			}
            return $response;
		}
        $response->getBody()->write((string)$payload);
        return $response;
	}
}