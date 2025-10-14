<?php declare(strict_types=1);

namespace APP;

use \CORE\Core;
use \Exception;

/**
 * App
 *
 * @author		Junyoung Park
 * @copyright	Copyright (c) 2016.
 * @license		LGPL
 * @version		1.0.0
 * @namespace	\APP
 */
class App
{

	/**
	 * run control
	 */
	public static function run(): void
	{
		$router = Core::get('Router');
		$router->dispatch();

		// Get details from the router after dispatching
		$f = $router->getFile();
		$c = $router->getControl();
		$m = $router->getMethod();
		$params = $router->getParams();

		// If control is empty, it means a closure was handled or 404 was thrown.
		if (empty($c)) {
			return;
		}

		self::loadAbstract('control');

		if (!empty($f) && \setRequire($f) === false) {
			throw new Exception('Controller file not found: ' . $f);
		}
		if (!class_exists($c)) {
			throw new Exception('Controller class not found: ' . $c);
		}
		if (!method_exists($c, $m)) {
			throw new Exception('Method not found in controller: ' . $c . '::' . $m);
		}

		// Dependencies for the controller
		$input    = Core::get('Input');
		$security = Core::get('Security');
		$session  = Core::get('Session');
		$crypt    = Core::get('Crypt');

		$control = new $c($f, $c, $m, $router, $input, $security, $session, $crypt);

		// Call the controller method and get the response
		$response = call_user_func_array([$control, $m], $params);

		// --- Automatic JSON Response Transformation ---
		self::handleResponse($response);
	}

	public static function handleResponse(mixed $response): void
	{
		if ($response === null) {
			http_response_code(204); // No Content
			return;
		}

		if (is_array($response) || is_object($response)) {
			header('Content-Type: application/json');

			// Set status code based on request method for successful responses
			if (!http_response_code() || http_response_code() < 300) {
				$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
				switch ($method) {
					case 'POST':
						http_response_code(201); // Created
						break;
					default:
						http_response_code(200); // OK
						break;
				}
			}
			echo json_encode($response);
		} else {
			// For other types, like string, just echo it (legacy support).
			echo $response;
		}
	}
}

/* End of file app.php */
/* Location: ./app/app.php */