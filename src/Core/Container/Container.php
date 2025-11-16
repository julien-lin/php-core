<?php

namespace JulienLinard\Core\Container;

use ReflectionClass;
use ReflectionParameter;

/**
 * Container d'injection de dépendances simple avec auto-wiring
 */
class Container
{
    /**
     * Bindings de services
     * Structure: ['abstract' => ['concrete' => callable, 'singleton' => bool]]
     */
    private array $bindings = [];

    /**
     * Instances de singletons
     */
    private array $instances = [];

    /**
     * Cache de ReflectionClass pour améliorer les performances
     */
    private array $reflectionCache = [];

    /**
     * Enregistre un binding
     *
     * @param string $abstract Nom abstrait ou classe
     * @param callable|string|null $concrete Classe concrète ou callable
     * @param bool $singleton Si true, retourne toujours la même instance
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => $singleton,
        ];
    }

    /**
     * Enregistre un singleton
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Résout une classe ou un binding
     *
     * @param string $abstract Nom abstrait ou classe
     * @param array $parameters Paramètres additionnels pour le constructeur
     * @return object Instance résolue
     */
    public function make(string $abstract, array $parameters = []): object
    {
        // Si c'est un singleton et qu'on a déjà une instance, la retourner
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Vérifier si on a un binding
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];

            // Si c'est un callable, l'exécuter
            if (is_callable($concrete)) {
                $instance = $concrete($this);
            } else {
                // Sinon, résoudre la classe
                $instance = $this->resolve($concrete, $parameters);
            }

            // Si c'est un singleton, stocker l'instance
            if ($binding['singleton']) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        }

        // Pas de binding, résoudre directement la classe
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Résout une classe avec auto-wiring
     *
     * @param string $class Nom de la classe
     * @param array $parameters Paramètres additionnels
     * @return object Instance de la classe
     */
    private function resolve(string $class, array $parameters = []): object
    {
        $reflection = $this->getReflection($class);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("La classe {$class} n'est pas instanciable.");
        }

        $constructor = $reflection->getConstructor();

        // Pas de constructeur, instancier directement
        if ($constructor === null) {
            return new $class();
        }

        // Résoudre les dépendances du constructeur
        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Résout les dépendances d'un constructeur
     *
     * @param ReflectionParameter[] $parameters Paramètres du constructeur
     * @param array $provided Paramètres fournis manuellement
     * @return array Arguments résolus
     */
    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Si le paramètre est fourni manuellement, l'utiliser
            if (isset($provided[$name])) {
                $dependencies[] = $provided[$name];
                continue;
            }

            // Si le paramètre a un type, essayer de le résoudre
            $type = $parameter->getType();
            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                try {
                    $dependencies[] = $this->make($typeName);
                    continue;
                } catch (\Throwable $e) {
                    // Si on ne peut pas résoudre et qu'il n'y a pas de valeur par défaut, lever une exception
                    if (!$parameter->isDefaultValueAvailable()) {
                        throw new \RuntimeException(
                            "Impossible de résoudre la dépendance {$name} de type {$typeName}."
                        );
                    }
                }
            }

            // Utiliser la valeur par défaut si disponible
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Impossible de résoudre le paramètre {$name} de la classe."
                );
            }
        }

        return $dependencies;
    }

    /**
     * Récupère ou crée une ReflectionClass avec cache
     */
    private function getReflection(string $class): \ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            if (!class_exists($class)) {
                throw new \RuntimeException("La classe {$class} n'existe pas.");
            }
            $this->reflectionCache[$class] = new \ReflectionClass($class);
        }
        return $this->reflectionCache[$class];
    }

    /**
     * Vérifie si un binding existe
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Supprime un binding
     */
    public function forget(string $abstract): void
    {
        unset($this->bindings[$abstract], $this->instances[$abstract]);
    }

    /**
     * Vide tous les bindings et instances
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->reflectionCache = [];
    }
}

