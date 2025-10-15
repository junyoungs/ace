<?php declare(strict_types=1);

namespace ACE;

use Psr\Http\Message\ServerRequestInterface;







abstract class Control
{
	public function __construct(
		public readonly ?string $file,
		public readonly ?string $class,
		public readonly ?string $method,
        public readonly ServerRequestInterface $request,
		public readonly Router $router,
		public readonly Input $input,
		public readonly Security $security,
		public readonly Session $session,
		public readonly Crypt $crypt
	) {}

	final public function db(string $driver, bool $master = false): DatabaseDriverInterface
	{
		return app('Db')->driver($driver, $master);
	}
}