<?php
declare(strict_types=1);
namespace App\Core;

use App\Core\Exceptions\ContainerException;

final class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, object> */
    private array $resolved = [];

    /** @var self|null */
    private static ?self $instance = null;

    // Singleton access for global container
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bind a factory closure to an abstract key.
     * Factory receives the container as argument.
     */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->singletons[$abstract] = false;
    }

    /**
     * Bind as singleton — resolved once, cached forever.
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->singletons[$abstract] = true;
    }

    /**
     * Register an already-constructed instance.
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->resolved[$abstract] = $instance;
        $this->singletons[$abstract] = true;
    }

    /**
     * Resolve and return an instance of the given class/abstract.
     */
    public function make(string $abstract): mixed
    {
        // Already resolved singleton?
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        // Has explicit binding?
        if (isset($this->bindings[$abstract])) {
            $instance = ($this->bindings[$abstract])($this);
            if ($this->singletons[$abstract] ?? false) {
                $this->resolved[$abstract] = $instance;
            }
            return $instance;
        }

        // Auto-wire via reflection
        $instance = $this->build($abstract);

        // Cache if it was declared singleton (even without explicit binding)
        if ($this->singletons[$abstract] ?? false) {
            $this->resolved[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build a class by resolving its constructor dependencies.
     */
    private function build(string $concrete): object
    {
        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new ContainerException("Class [{$concrete}] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class [{$concrete}] is not instantiable (abstract or interface).");
        }

        $constructor = $reflector->getConstructor();

        // No constructor — just instantiate
        if ($constructor === null) {
            return new $concrete();
        }

        $params = $constructor->getParameters();
        $dependencies = [];

        foreach ($params as $param) {
            $type = $param->getType();

            // No type hint
            if ($type === null || !($type instanceof \ReflectionNamedType) || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve parameter [{$param->getName()}] of [{$concrete}] — no type hint or default value."
                    );
                }
                continue;
            }

            // Class type hint — resolve recursively
            try {
                $dependencies[] = $this->make($type->getName());
            } catch (ContainerException $e) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Check if abstract is bound or resolvable.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->resolved[$abstract]);
    }
}
