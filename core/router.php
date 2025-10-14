<?php declare(strict_types=1);

namespace CORE;

use \BOOT\Log;
use \Exception;
use \ReflectionClass;
use \ReflectionMethod;

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
            $className = get_class_from_file($phpFile->getRealPath());
            if (!$className || !class_exists($className)) continue;

            $reflection = new ReflectionClass($className);
            $resourceName = strtolower(str_replace('Controller', '', $reflection->getShortName()));
            $baseUri = "/api/{$resourceName}";

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $methodName = $method->getName();
                preg_match('/^(get|post|put|delete)(.*)$/', $methodName, $matches);

                if (empty($matches)) continue;

                $httpMethod = strtoupper($matches[1]);
                $actionPath = strtolower($matches[2]);

                $uri = "{$baseUri}/{$actionPath}";

                $params = $method->getParameters();
                foreach($params as $param) {
                    $uri .= "/{{$param->getName()}}";
                }

                self::$routeMap[$httpMethod][$uri] = [
                    'action' => [$className, $methodName],
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

function get_class_from_file(string $path): ?string
{
    $content = file_get_contents($path);
    $tokens = token_get_all($content);
    $namespace = '';
    for ($i = 0; $i < count($tokens); $i++) {
        if ($tokens[$i][0] === T_NAMESPACE) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if ($tokens[$j] === ';') {
                    break;
                }
                $namespace .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
        }
        if ($tokens[$i][0] === T_CLASS) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if ($tokens[$j] === '{') {
                    $className = $tokens[$i+2][1];
                    return trim($namespace) . '\\' . $className;
                }
            }
        }
    }
    return null;
}