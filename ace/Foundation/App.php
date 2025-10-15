<?php declare(strict_types=1);

namespace ACE\Foundation;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response as Psr7Response;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

class App
{
	public static function run(): void
	{
        $request = ServerRequestFactory::fromGlobals();
        $response = self::handle($request);
        (new SapiEmitter())->emit($response);
	}

    public static function handle(ServerRequestInterface $request): ResponseInterface
    {
        $container = Core::getInstance();
        $container->flushRequestState();
        $container->singleton('Request', fn() => $request);

        try {
            $router = $container->get('Router');
            $router->dispatch($request->getUri()->getPath(), $request->getMethod());

            $controllerClass = $router->getControl();
            $method = $router->getMethod();
            $params = $router->getParams();

            $responsePayload = null;
            if (!empty($controllerClass)) {
                $controller = new $controllerClass(
                    $router->getFile(), $controllerClass, $method, $request,
                    $router, $container->get('Input'), $container->get('Security'),
                    $container->get('Session'), $container->get('Crypt')
                );
                $responsePayload = call_user_func_array([$controller, $method], $params);
            }
            return self::createResponse($responsePayload, $request->getMethod());

        } catch (\Throwable $e) {
            return new Psr7Response(
                json_encode(['error' => $e->getMessage()]),
                $e->getCode() >= 400 ? $e->getCode() : 500,
                ['Content-Type' => 'application/json']
            );
        }
    }

	private static function createResponse(mixed $payload, string $httpMethod): ResponseInterface
	{
        $response = new Psr7Response();
        if ($payload === null) return $response->withStatus(204);
		if (is_array($payload) || is_object($payload)) {
			$response->getBody()->write(json_encode($payload));
            $response = $response->withHeader('Content-Type', 'application/json');
			$statusCode = $response->getStatusCode();
			if ($statusCode < 300) {
				switch ($httpMethod) {
					case 'POST': return $response->withStatus(201);
					default: return $response->withStatus(200);
				}
			}
            return $response;
		}
        $response->getBody()->write((string)$payload);
        return $response;
	}
}