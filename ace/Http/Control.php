<?php declare(strict_types=1);

namespace ACE\Http;

use Psr\Http\Message\ServerRequestInterface;
use ACE\Database\DatabaseDriverInterface;

abstract class Control
{
	public function __construct(
        public readonly ServerRequestInterface $request
	) {}

	final protected function db(string $driver, bool $master = false): DatabaseDriverInterface
	{
		return app(Db::class)->driver($driver, $master);
	}
}