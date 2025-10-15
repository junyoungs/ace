<?php declare(strict_types=1);

namespace ACE\Http;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use ACE\Support\Log;

class Router
{
    public string $uri = '';
    public string $file = '';
    public string $control = '';
    public string $method = '';
    public array $params = [];
    private static array $routeMap = [];

    public function __construct()
    {
        $this->buildRouteMap();
        Log::w('INFO', 'Router class initialized and routes registered.');
    }

    private function buildRouteMap(): void
    {
        if (!empty(self::$routeMap)) return;

        $controllerPath = PROJECT_ROOT . '/app/Http/Controllers';
        if (!is_dir($controllerPath)) return;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerPath));
        $phpFiles = new \RegexIterator($files, '/\.php$/');

        foreach ($phpFiles as $phpFile) {
            $className = $this->getClassNameFromFile($phpFile->getRealPath());
            if (!$className || !class_exists($className)) continue;

            $reflection = new ReflectionClass($className);
            $resourceName = strtolower(str_replace('Controller', '', $reflection->getShortName()));
            $baseUri = "/api/{$resourceName}";

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                preg_match('/^(get|post|put|delete)(.*)$/', $method->getName(), $matches);
                if (empty($matches)) continue;

                $httpMethod = strtoupper($matches[1]);
                $actionPath = strtolower($matches[2]);
                $uri = "{$baseUri}/{$actionPath}";

                foreach($method->getParameters() as $param) {
                    $uri .= "/{{$param->getName()}}";
                }

                self::$routeMap[$httpMethod][$uri] = [
                    'action' => [$className, $method->getName()],
                    'file'   => $phpFile->getRealPath(),
                ];
            }
        }
    }

    public function dispatch(string $requestUri, string $httpMethod): void
    {
        $routesForMethod = self::$routeMap[strtoupper($httpMethod)] ?? [];

        foreach ($routesForMethod as $uriPattern => $routeData) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $uriPattern);
            if (preg_match('#^' . $pattern . '$#', $requestUri, $matches)) {
                array_shift($matches);
                $this->uri = $requestUri;
                $this->params = $matches;
                $this->control = $routeData['action'][0];
                $this->method = $routeData['action'][1];
                $this->file = $routeData['file'];
                return;
            }
        }
        $this->handleNotFound($requestUri, $httpMethod);
    }

    private function handleNotFound(string $uri, string $method): void
    {
        throw new Exception("404 Not Found: No route matched for [{$method}] {$uri}", 404);
    }

    private function getClassNameFromFile(string $path): ?string { /* ... */ }
    public function getControl(): string { return $this->control; }
    public function getMethod(): string { return $this->method; }
    public function getFile(): string { return $this->file; }
    public function getParams(): array { return $this->params; }
}