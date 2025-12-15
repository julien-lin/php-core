# Container d'Injection de Dépendances

**Version** : 1.4.4+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le Container de `core-php` est un conteneur d'injection de dépendances avec auto-wiring automatique. Il utilise la réflexion PHP pour résoudre automatiquement les dépendances des constructeurs, évitant ainsi la configuration manuelle dans la plupart des cas.

---

## Fonctionnalités Principales

### 1. Auto-wiring

Le Container résout automatiquement les dépendances en analysant les types des paramètres du constructeur via Reflection.

**Exemple** :
```php
class UserService
{
    public function __construct(
        private Database $db,
        private LoggerInterface $logger
    ) {}
}

// Résolution automatique
$service = $container->make(UserService::class);
// $db et $logger sont automatiquement injectés
```

### 2. Bindings

Vous pouvez enregistrer des bindings personnalisés pour remplacer la résolution automatique.

**Types de bindings** :
- **Classe concrète** : Remplace une interface par une implémentation
- **Callable** : Factory function pour créer l'instance
- **Singleton** : Instance unique partagée

**Exemple** :
```php
// Binding simple
$container->bind(LoggerInterface::class, SimpleLogger::class);

// Binding avec factory
$container->bind(Database::class, function(Container $container) {
    return new Database($container->make(Config::class));
});

// Singleton
$container->singleton(Cache::class, function() {
    return new RedisCache();
});
```

### 3. Cache de Requête

Le Container maintient un cache de requête pour les instances non-singleton. Ce cache est automatiquement nettoyé après chaque requête pour éviter les fuites mémoire.

**Avantages** :
- Performance : Évite de résoudre plusieurs fois la même classe dans une requête
- Mémoire : Nettoyage automatique après chaque requête

**Exemple** :
```php
// Première résolution : création de l'instance
$service1 = $container->make(UserService::class);

// Deuxième résolution : réutilisation du cache
$service2 = $container->make(UserService::class);

// $service1 et $service2 sont la même instance (durant la requête)
// Mais une nouvelle instance sera créée pour la prochaine requête
```

### 4. Protection contre les Dépendances Circulaires

Le Container détecte et empêche les dépendances circulaires.

**Exemple de dépendance circulaire** :
```php
class A {
    public function __construct(B $b) {}
}
class B {
    public function __construct(A $a) {}
}
```

**Résultat** :
```
RuntimeException: Circular dependency detected for class A.
Dependency chain: A → B → A
```

### 5. Limite de Profondeur

Le Container limite la profondeur de résolution à 20 niveaux pour éviter les chaînes infinies.

**Exemple** :
```php
class A1 { public function __construct(A2 $a2) {} }
class A2 { public function __construct(A3 $a3) {} }
// ... jusqu'à A20
class A20 { public function __construct(A21 $a21) {} }
```

**Résultat** :
```
RuntimeException: Max resolution depth (20) exceeded
```

---

## API du Container

### Méthodes Principales

#### `bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void`

Enregistre un binding.

**Paramètres** :
- `$abstract` : Nom abstrait ou classe
- `$concrete` : Classe concrète ou callable (null = même que abstract)
- `$singleton` : Si true, retourne toujours la même instance

**Exemple** :
```php
$container->bind(LoggerInterface::class, SimpleLogger::class);
$container->bind('db', Database::class, true); // Singleton
```

#### `singleton(string $abstract, callable|string|null $concrete = null): void`

Enregistre un singleton (raccourci pour `bind(..., true)`).

**Exemple** :
```php
$container->singleton(Cache::class, RedisCache::class);
```

#### `make(string $abstract, array $parameters = []): mixed`

Résout une classe ou un binding.

**Paramètres** :
- `$abstract` : Nom abstrait ou classe
- `$parameters` : Paramètres additionnels pour le constructeur

**Exemple** :
```php
// Résolution simple
$service = $container->make(UserService::class);

// Avec paramètres personnalisés
$user = $container->make(User::class, ['id' => 123]);
```

#### `has(string $abstract): bool`

Vérifie si un binding existe.

**Exemple** :
```php
if ($container->has(LoggerInterface::class)) {
    // Binding existe
}
```

#### `forget(string $abstract): void`

Supprime un binding.

**Exemple** :
```php
$container->forget(LoggerInterface::class);
```

#### `clearRequestCache(): void`

Vide le cache de requête (appelé automatiquement après chaque requête).

**Exemple** :
```php
$container->clearRequestCache();
```

#### `flush(): void`

Vide tous les bindings, instances et caches.

**Exemple** :
```php
$container->flush();
```

---

## Exemples d'Utilisation

### Exemple 1 : Service Simple

```php
class EmailService
{
    public function __construct(
        private Config $config,
        private LoggerInterface $logger
    ) {}
    
    public function send(string $to, string $subject, string $body): void
    {
        // Envoi d'email
    }
}

// Utilisation
$container = $app->getContainer();
$emailService = $container->make(EmailService::class);
$emailService->send('user@example.com', 'Test', 'Body');
```

### Exemple 2 : Interface avec Implémentation

```php
interface PaymentGateway
{
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGateway
{
    public function charge(float $amount): bool { return true; }
}

// Enregistrer le binding
$container->bind(PaymentGateway::class, StripeGateway::class);

// Utilisation
$gateway = $container->make(PaymentGateway::class);
// Retourne une instance de StripeGateway
```

### Exemple 3 : Singleton

```php
class Database
{
    private static ?self $instance = null;
    
    public function __construct(private string $dsn) {}
}

// Enregistrer comme singleton
$container->singleton(Database::class, function() {
    return new Database('mysql:host=localhost;dbname=app');
});

// Utilisation
$db1 = $container->make(Database::class);
$db2 = $container->make(Database::class);
// $db1 === $db2 (même instance)
```

### Exemple 4 : Factory avec Container

```php
class UserRepository
{
    public function __construct(
        private Database $db,
        private Cache $cache
    ) {}
}

// Factory qui utilise le Container
$container->bind(UserRepository::class, function(Container $container) {
    return new UserRepository(
        $container->make(Database::class),
        $container->make(Cache::class)
    );
});
```

### Exemple 5 : Paramètres Personnalisés

```php
class User
{
    public function __construct(
        private int $id,
        private string $name,
        private ?EmailService $emailService = null
    ) {}
}

// Résolution avec paramètres personnalisés
$user = $container->make(User::class, [
    'id' => 123,
    'name' => 'John Doe'
]);
// $emailService sera automatiquement résolu si disponible
```

---

## Optimisations de Performance

### 1. Cache de ReflectionClass

Le Container met en cache les objets `ReflectionClass` pour éviter de les recréer à chaque résolution.

**Impact** : Réduction de 50-70% du temps de résolution pour les classes fréquemment utilisées.

### 2. Cache de Requête

Les instances non-singleton sont mises en cache pour la durée d'une requête.

**Impact** : Évite de résoudre plusieurs fois la même classe dans une requête.

**Limitation** : Le cache de requête ne fonctionne que pour les résolutions sans paramètres personnalisés.

### 3. Nettoyage Automatique

Le cache de requête est automatiquement nettoyé après chaque requête via `Application::shutdown()`.

---

## Bonnes Pratiques

### 1. Utiliser des Interfaces

Préférez les interfaces aux classes concrètes pour faciliter les tests et la flexibilité.

```php
// ✅ Bon
$container->bind(LoggerInterface::class, SimpleLogger::class);

// ❌ Moins flexible
$container->bind(SimpleLogger::class, SimpleLogger::class);
```

### 2. Singletons pour les Services Coûteux

Utilisez des singletons pour les services qui sont coûteux à créer (Database, Cache, etc.).

```php
$container->singleton(Database::class, function() {
    return new Database($dsn);
});
```

### 3. Éviter les Dépendances Circulaires

Si vous avez des dépendances circulaires, utilisez des setters ou des factories.

```php
// ❌ Dépendance circulaire
class A { public function __construct(B $b) {} }
class B { public function __construct(A $a) {} }

// ✅ Solution : Setter
class A {
    private ?B $b = null;
    public function setB(B $b): void { $this->b = $b; }
}
```

### 4. Paramètres avec Valeurs par Défaut

Utilisez des valeurs par défaut pour les paramètres optionnels.

```php
class Service
{
    public function __construct(
        private Database $db,
        private ?Cache $cache = null
    ) {}
}
```

---

## Intégration avec le Router

Le Container est automatiquement utilisé par le Router pour injecter les dépendances dans les contrôleurs.

**Exemple** :
```php
class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private LoggerInterface $logger
    ) {}
    
    public function show(Request $request): Response
    {
        $user = $this->userService->find($request->getPathParam('id'));
        return $this->view('user/show', ['user' => $user]);
    }
}
```

Le Router résout automatiquement `UserService` et `LoggerInterface` via le Container.

---

## Limitations

1. **Types primitifs** : Le Container ne peut pas résoudre automatiquement les types primitifs (int, string, etc.). Utilisez des paramètres personnalisés.

2. **Union Types** : Les union types ne sont pas supportés pour l'auto-wiring.

3. **Paramètres nommés** : Les paramètres doivent être nommés dans le tableau `$parameters`.

4. **Profondeur** : Limite de 20 niveaux de profondeur pour éviter les chaînes infinies.

---

## Dépannage

### Erreur : "Impossible de résoudre la dépendance"

**Cause** : Le Container ne peut pas résoudre un type.

**Solution** :
1. Vérifier que la classe existe
2. Enregistrer un binding si c'est une interface
3. Fournir des paramètres personnalisés

### Erreur : "Circular dependency detected"

**Cause** : Dépendance circulaire détectée.

**Solution** :
1. Refactoriser pour éviter la dépendance circulaire
2. Utiliser des setters au lieu du constructeur
3. Utiliser un factory pattern

### Erreur : "Max resolution depth exceeded"

**Cause** : Chaîne de dépendances trop profonde (> 20 niveaux).

**Solution** :
1. Réduire la profondeur de dépendances
2. Utiliser des singletons pour les services intermédiaires

---

**Note** : Le Container est automatiquement créé et configuré par `Application`. Vous pouvez y accéder via `$app->getContainer()`.

