<?php declare(strict_types=1);

namespace ACE\Foundation;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response as Psr7Response;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use ACE\Http\Router;
use ACE\Kernel;

class App
{
	public static function run(): void
	{
        $request = ServerRequestFactory::fromGlobals();
        $kernel = new Kernel();
        $response = $kernel->handle($request);
        (new SpiEmitter())->emit($response);
	}
}