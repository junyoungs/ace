<?php namespace CORE;

use \BOOT\Log;

/**
 * Router
 *
 * This class now acts as a dispatcher for the new Route system.
 *
 * @author		Junyoung Park (Original), Jules (Refactor)
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		2.0.0
 * @namespace	\CORE
 */
class Router
{
    public $uri = '';
    public $file = '';
    public $control = '';
    public $method = '';
    public $params = []; // For route parameters

    public function __construct()
    {
        Log::w('INFO', '\\CORE\\Router class initialized.');
    }

    /**
     * Dispatch the request.
     * This method replaces the old run() method.
     */
    public function dispatch()
    {
        // Load the routes definition file
        $routesPath = PROJECTPATH . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        if (file_exists($routesPath)) {
            require_once $routesPath;
        }

        // Detect URI and HTTP method
        $requestUri = $this->__detect();
        $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Find a matching route
        $matchedRoute = Route::dispatch($httpMethod, '/' . $requestUri);

        if ($matchedRoute) {
            $this->uri = $requestUri;
            $this->params = $matchedRoute['params'];
            $action = $matchedRoute['action'];

            if ($action instanceof \Closure) {
                // Execute the closure with parameters
                call_user_func_array($action, $this->params);
                // Stop further execution since this is a closure-based route
                exit;
            } else {
                // Handle Controller@method string
                $parts = explode('@', $action);
                $controlName = $parts[0];
                $methodName = $parts[1] ?? 'index';

                // Append 'Controller' suffix if not present
                if (substr($controlName, -10) !== 'Controller') {
                    $controlName .= 'Controller';
                }

                $this->setControl($controlName);
                $this->setMethod($methodName);

                // Assumes PSR-4 like structure under host/{HOST}/control/
                // e.g., 'User' -> host/localhost/control/UserController.php
                $this->setFile(HOSTPATH . DIRECTORY_SEPARATOR . 'control' . DIRECTORY_SEPARATOR . $this->getControl() . '.php');
            }
        } else {
            // No route matched, handle 404
            $this->handleNotFound();
        }
    }

    private function handleNotFound()
    {
        if (MODE == 'development') {
            throw new \Exception("404 Not Found: No route matched for [{$_SERVER['REQUEST_METHOD']}] /{$this->__detect()}");
        } else {
            header('HTTP/1.1 404 Not Found');
            // You might want to have a dedicated 404 view/controller
            echo "<h1>404 Not Found</h1>";
            exit;
        }
    }

    // Keep the URI detection and filtering logic from the original Router
    private function __detect()
	{
		if ( ! isset($_SERVER['REQUEST_URI']) || ! isset($_SERVER['SCRIPT_NAME']))
		{
			return '';
		}

		$uri = $_SERVER['REQUEST_URI'];
		if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0)
		{
			$uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
		}
		else if (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0)
		{
			$uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
		}

		if (strncmp($uri, '?/', 2) === 0)
		{
			$uri = substr($uri, 2);
		}

        $parts = preg_split('#\?#i', $uri, 2);
		$uri = $parts[0];

		return trim(parse_url($uri, PHP_URL_PATH), '/');
	}

    // Basic setters and getters
    public function setControl($control) { $this->control = str_replace('.php', '', $control); }
    public function getControl() { return $this->control; }
    public function setMethod($method) { $this->method = $method; }
    public function getMethod() { return $this->method; }
    public function setFile($file) { $this->file = $file; }
    public function getFile() { return $this->file; }
    public function getParams() { return $this->params; }
}