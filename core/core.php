<?php declare(strict_types=1);

namespace CORE;

use Exception;

/**
 * Core Service Registry
 */
class Core
{
	/** @var array<string, object> */
	private static array $__instance  = [];

	public function __construct()
	{
		$this->registerCoreServices();
	}

	private function registerCoreServices(): void
	{
		// Manually register core singletons.
		// In a more advanced container, this would be automated.
		self::set('Db', new Db());
		self::set('Crypt', new Crypt());
		self::set('Session', new Session());
		self::set('Security', new Security());
		self::set('Router', new Router());
		self::set('Input', new Input());
		self::set('Output', new Output());

		if (defined('MODE') && MODE === 'development') {
			self::set('Dev', new Dev());
		}
	}

	public static function get(string $name): ?object
	{
		return self::$__instance[$name] ?? null;
	}

	public static function set(string $name, object $instance): void
	{
		if (!isset(self::$__instance[$name])) {
			self::$__instance[$name] = $instance;
		}
	}
}