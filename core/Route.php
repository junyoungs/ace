<?php

namespace CORE;

class Route
{
    /**
     * All registered routes.
     *
     * @var array
     */
    protected static $routes = [];

    /**
     * The current group attributes.
     *
     * @var array
     */
    protected static $groupAttributes = [];

    /**
     * Register a GET route.
     *
     * @param string $uri
     * @param mixed $action
     */
    public static function get($uri, $action)
    {
        self::addRoute('GET', $uri, $action);
    }

    /**
     * Register a POST route.
     *
     * @param string $uri
     * @param mixed $action
     */
    public static function post($uri, $action)
    {
        self::addRoute('POST', $uri, $action);
    }

    /**
     * Register a PUT route.
     *
     * @param string $uri
     * @param mixed $action
     */
    public static function put($uri, $action)
    {
        self::addRoute('PUT', $uri, $action);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $uri
     * @param mixed $action
     */
    public static function delete($uri, $action)
    {
        self::addRoute('DELETE', $uri, $action);
    }

    /**
     * Create a route group.
     *
     * @param array $attributes
     * @param callable $callback
     */
    public static function group(array $attributes, callable $callback)
    {
        $originalGroupAttributes = self::$groupAttributes;
        self::$groupAttributes = array_merge($originalGroupAttributes, $attributes);

        call_user_func($callback);

        self::$groupAttributes = $originalGroupAttributes;
    }

    /**
     * Add a route to the routes array.
     *
     * @param string $method
     * @param string $uri
     * @param mixed $action
     */
    protected static function addRoute($method, $uri, $action)
    {
        $prefix = self::$groupAttributes['prefix'] ?? '';
        $uri = rtrim($prefix, '/') . '/' . ltrim($uri, '/');

        self::$routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action
        ];
    }

    /**
     * Dispatch the request to the appropriate route.
     *
     * @param string $httpMethod
     * @param string $requestUri
     * @return mixed
     */
    public static function dispatch($httpMethod, $requestUri)
    {
        foreach (self::$routes as $route) {
            // Convert route URI to regex, e.g., /users/{id} -> /users/([^/]+)
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $httpMethod && preg_match($pattern, $requestUri, $matches)) {
                array_shift($matches); // Remove the full match
                $params = $matches;

                return ['action' => $route['action'], 'params' => $params];
            }
        }

        return null; // No route matched
    }
}