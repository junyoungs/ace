<?php declare(strict_types=1);

namespace ACE\Http;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private array $routeMap = [];

    public function __construct()
    {
        $this->buildRouteMap();
    }

    private function buildRouteMap(): void
    {
        if (!empty($this->routeMap)) return;

        $controllerPath = BASE_PATH . '/app/Http/Controllers';
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
                $uri = rtrim("{$baseUri}/{$actionPath}", '/');

                $params = [];
                foreach($method->getParameters() as $param) {
                    $uri .= "/{{$param->getName()}}";
                    $params[] = $param->getName();
                }

                $uriPattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $uri);
                $this->routeMap[$httpMethod][$uriPattern] = [
                    'class'  => $className,
                    'method' => $method->getName(),
                    'params' => $params,
                ];
            }
        }
    }

    public function dispatch(ServerRequestInterface $request): array
    {
        $requestUri = $request->getUri()->getPath();
        $httpMethod = $request->getMethod();
        $host = $request->getUri()->getHost();
        $subdomain = explode('.', $host)[0];

        $appKernel = new \APP\Http\Kernel();
        $middleware = array_merge(
            $appKernel->middleware,
            $appKernel->middlewareGroups[$subdomain] ?? $appKernel->middlewareGroups['*'] ?? []
        );

        $routesForMethod = $this->routeMap[$httpMethod] ?? [];

        foreach ($routesForMethod as $uriPattern => $routeData) {
            if (preg_match('#^' . $uriPattern . '$#', $requestUri, $matches)) {
                array_shift($matches);
                return [
                    'class'      => $routeData['class'],
                    'method'     => $routeData['method'],
                    'params'     => $matches,
                    'middleware' => $middleware,
                ];
            }
        }
        throw new Exception("404 Not Found: No route matched for [{$httpMethod}] {$requestUri}", 404);
    }

    private function getClassNameFromFile(string $path): ?string
    {
        $content = file_get_contents($path);
        if (!$content) {
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        $className = null;
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = trim($matches[1]);
        }

        if ($namespace && $className) {
            return "{$namespace}\\{$className}";
        }

        return $className;
    }
}