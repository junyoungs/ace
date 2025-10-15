<?php declare(strict_types=1);

namespace ACE\Foundation;

use Exception;
use ReflectionClass;
use ReflectionNamedType;
// ... other use statements

class Kernel
{
    // ... properties

	public function __construct() { /* ... */ }
    public static function getInstance(): self { /* ... */ }
	private function registerCoreServices(): void { /* ... */ }
    public function bind(string $id, callable $resolver): void { /* ... */ }
    public function singleton(string $id, callable $resolver): void { /* ... */ }
    public function flushRequestState(): void { /* ... */ }
    public static function run(): void { /* ... */ }
    public function handle(ServerRequestInterface $request): ResponseInterface { /* ... */ }
	private function createResponse(mixed $payload, string $httpMethod): ResponseInterface { /* ... */ }

	public function get(string $id): ?object
	{
        if (isset($this->instances[$id])) return $this->instances[$id];

        // If we have a binding, resolve it.
        if (isset($this->bindings[$id])) {
            $instance = ($this->bindings[$id])($this);
            if ($this->isShared[$id]) $this->instances[$id] = $instance;
            return $instance;
        }

        // Otherwise, try to auto-wire the class
        if (!class_exists($id)) {
            throw new Exception("Service not found and cannot be auto-wired: {$id}");
        }

        $reflection = new ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new Exception("Class {$id} is not instantiable.");
        }

        $constructor = $reflection->getConstructor();
        if (is_null($constructor)) {
            return new $id(); // No constructor, just instantiate.
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin() && $type instanceof ReflectionNamedType) {
                $dependencies[] = $this->get($type->getName());
            } else {
                // Cannot resolve built-in types or untyped parameters
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve constructor parameter '{$parameter->getName()}' for class {$id}");
                }
            }
        }

        $instance = $reflection->newInstanceArgs($dependencies);

        // If the auto-wired class is a controller or service, it's not shared by default.
        // You could add logic here to make them singletons if needed.
        return $instance;
	}
}