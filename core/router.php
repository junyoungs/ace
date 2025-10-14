<?php declare(strict_types=1);

namespace CORE;

use \BOOT\Log;
use \Exception;
use \ReflectionClass;
use \APP\Attributes\Route;

class Router
{
    public string $uri = '';
    public string $file = '';
    public string $control = '';
    public string $method = '';

    /** @var array<int, mixed> */
    public array $params = [];

    /** @var array<string, array<string, array{action: array{string, string}, file: string}>> */
    private static array $routeMap = [];

    public function __construct()
    {
        $this->buildRouteMap();
        Log::w('INFO', '\\CORE\\Router class initialized and routes registered.');
    }

    private function buildRouteMap(): void
    {
        if (!empty(self::$routeMap)) {
            return;
        }

        $controllerPath = __DIR__ . '/../app/Http/Controllers';
        if (!is_dir($controllerPath)) return;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerPath));
        $phpFiles = new \RegexIterator($files, '/\.php$/');

        foreach ($phpFiles as $phpFile) {
            require_once $phpFile->getRealPath();
            $className = $this->getClassNameFromFile($phpFile->getRealPath());

            if (!$className || !class_exists($className)) continue;

            $reflection = new ReflectionClass($className);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);
                foreach ($attributes as $attribute) {
                    $route = $attribute->newInstance();
                    $httpMethod = strtoupper($route->method);
                    self::$routeMap[$httpMethod][$route->uri] = [
                        'action' => [$className, $method->getName()],
                        'file'   => $phpFile->getRealPath(),
                    ];
                }
            }
        }
    }

    public function dispatch(): void
    {
        $requestUri = '/' . $this->__detect();
        $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $routesForMethod = self::$routeMap[$httpMethod] ?? [];

        foreach ($routesForMethod as $uriPattern => $routeData) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $uriPattern);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $requestUri, $matches)) {
                array_shift($matches);

                $this->uri = $requestUri;
                $this->params = $matches;
                $this->control = $routeData['action'][0];
                $this->method = $routeData['action'][1];
                $this->file = $routeData['file'];
                return;
            }
        }

        $this->handleNotFound();
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
            // This assumes no namespace for controllers, which is the current project state.
            return $matches[1];
        }
        return null;
    }

    private function handleNotFound(): void
    {
        if (MODE === 'development') {
            throw new Exception("404 Not Found: No route matched for [{$_SERVER['REQUEST_METHOD']}] {$this->__detect()}");
        } else {
            header('HTTP/1.1 404 Not Found');
            echo "<h1>404 Not Found</h1>";
            exit;
        }
    }

    private function __detect(): string
	{
		if ( ! isset($_SERVER['REQUEST_URI']) || ! isset($_SERVER['SCRIPT_NAME'])) return '';
		$uri = $_SERVER['REQUEST_URI'];
		if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
			$uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
		} elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
			$uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
		}
		if (strncmp($uri, '?/', 2) === 0) $uri = substr($uri, 2);
        $parts = preg_split('#\?#i', $uri, 2);
		$uri = $parts[0];
		return trim(parse_url($uri, PHP_URL_PATH) ?? '', '/');
	}

    public function getControl(): string { return $this->control; }
    public function getMethod(): string { return $this->method; }
    public function getFile(): string { return $this->file; }
    public function getParams(): array { return $this->params; }
}