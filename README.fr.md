# Core PHP - Framework MVC Complet

[🇫🇷 Lire en français](README.fr.md) | [🇬🇧 Read in English](README.md)

## 💝 Soutenir le projet

Si ce package vous est utile, envisagez de [devenir un sponsor](https://github.com/sponsors/julien-lin) pour soutenir le développement et la maintenance de ce projet open source.

---

Un framework MVC moderne et complet pour PHP 8+ avec Container DI, Controllers, Views, Forms, Session, Cache et plus.

## 🚀 Installation

```bash
composer require julienlinard/core-php
```

**Requirements** : PHP 8.0 ou supérieur

## ⚡ Démarrage rapide

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Core\Application;
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Core\View\View;

// Bootstrap de l'application
$app = Application::create(__DIR__);
$app->start();
```

## 📋 Fonctionnalités

- ✅ **Application** - Classe principale du framework
- ✅ **Container DI** - Injection de dépendances avec auto-wiring
- ✅ **Controllers** - Classe de base avec méthodes utilitaires
- ✅ **Views** - Moteur de templates avec layouts
- ✅ **Models** - Classe Model de base avec hydratation
- ✅ **Forms** - Validation de formulaires et gestion d'erreurs (alimenté par php-validator)
- ✅ **Session** - Gestion des sessions avec flash messages
- ✅ **Cache** - Système de cache intégré (php-cache)
- ✅ **Middleware** - Système de middlewares intégré
- ✅ **Config** - Gestion de la configuration
- ✅ **Exceptions** - Gestion centralisée des erreurs

## 📖 Documentation

### Application

```php
use JulienLinard\Core\Application;

// Créer une instance de l'application
$app = Application::create(__DIR__);

// Récupérer l'instance existante (peut retourner null)
$app = Application::getInstance();

// Récupérer l'instance ou la créer si elle n'existe pas (utile pour les gestionnaires d'erreurs)
$app = Application::getInstanceOrCreate(__DIR__);

// Récupérer l'instance ou lancer une exception si elle n'existe pas
$app = Application::getInstanceOrFail();

// Configurer les chemins des vues
$app->setViewsPath(__DIR__ . '/views');
$app->setPartialsPath(__DIR__ . '/views/_templates');

// Démarrer l'application
$app->start();
```

### Controllers

```php
use JulienLinard\Core\Controller\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return $this->view('home/index', [
            'title' => 'Accueil',
            'data' => []
        ]);
    }
    
    public function redirect()
    {
        return $this->redirect('/login');
    }
    
    public function json()
    {
        return $this->json(['message' => 'Hello']);
    }
}
```

### Views

```php
use JulienLinard\Core\View\View;

// Vue complète avec layout
$view = new View('home/index');
$view->render(['title' => 'Accueil']);

// Vue partielle (sans layout)
$view = new View('partials/header', false);
$view->render();
```

### Models

```php
use JulienLinard\Core\Model\Model;

class User extends Model
{
    public ?int $id = null;
    public string $email;
    public string $name;
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
}

// Hydratation automatique
$user = new User(['id' => 1, 'email' => 'test@example.com', 'name' => 'John']);
```

### Forms & Validation

`core-php` inclut `php-validator` pour une validation de formulaires avancée avec règles personnalisées, messages multilingues et sanitization.

#### Utilisation de la méthode validate() (Recommandé)

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();
$result = $validator->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:8|max:255',
    'age' => 'required|numeric|min:18'
]);

if ($result->hasErrors()) {
    // Récupérer toutes les erreurs
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
    
    // Récupérer les erreurs d'un champ spécifique
    $emailErrors = $result->getErrorsForField('email');
} else {
    // Validation réussie
}
```

#### Fonctionnalités avancées

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();

// Messages d'erreur personnalisés
$validator->setCustomMessages([
    'email.email' => 'Veuillez entrer une adresse email valide',
    'password.min' => 'Le mot de passe doit contenir au moins 8 caractères'
]);

// Définir la locale pour les messages multilingues
$validator->setLocale('fr');

// Activer/désactiver la sanitization automatique
$validator->setSanitize(true);

// Enregistrer des règles de validation personnalisées
$validator->registerRule(new CustomRule());

// Valider
$result = $validator->validate($data, $rules);
```

#### Validation manuelle (Méthode legacy)

```php
use JulienLinard\Core\Form\FormResult;
use JulienLinard\Core\Form\FormError;
use JulienLinard\Core\Form\FormSuccess;
use JulienLinard\Core\Form\Validator;

$formResult = new FormResult();
$validator = new Validator();

// Validation manuelle
if (!$validator->required($data['email'])) {
    $formResult->addError(new FormError('Email requis'));
}

if (!$validator->email($data['email'])) {
    $formResult->addError(new FormError('Email invalide'));
}

if ($formResult->hasErrors()) {
    // Gérer les erreurs
} else {
    $formResult->addSuccess(new FormSuccess('Formulaire validé'));
}
```

### Session

```php
use JulienLinard\Core\Session\Session;

// Définir une valeur
Session::set('user_id', 123);

// Récupérer une valeur
$userId = Session::get('user_id');

// Flash message
Session::flash('success', 'Opération réussie');

// Supprimer
Session::remove('user_id');
```

### Container DI

```php
use JulienLinard\Core\Container\Container;

$container = new Container();

// Binding simple
$container->bind('database', function() {
    return new Database();
});

// Singleton
$container->singleton('logger', function() {
    return new Logger();
});

// Résolution automatique
$service = $container->make(MyService::class);
```

## 🔗 Intégration avec les autres packages

### Configuration centralisée

Le framework permet de charger la configuration depuis des fichiers PHP dans un répertoire `config/`.

```php
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);

// Charger la configuration depuis config/
$app->loadConfig('config');

// Les fichiers config/app.php, config/database.php, etc. sont automatiquement chargés
// Accessible via $app->getConfig()->get('app.name')
```

**Structure recommandée** :
```
config/
  app.php      # Configuration de l'application
  database.php # Configuration de la base de données
  cache.php    # Configuration du cache
```

**Exemple config/app.php** :
```php
<?php
return [
    'name' => 'Mon Application',
    'debug' => true,
    'timezone' => 'Europe/Paris',
];
```

### Intégration avec php-router

`core-php` inclut automatiquement `php-router`. Le router est accessible via `getRouter()`.

```php
use JulienLinard\Core\Application;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// Définir des routes dans vos contrôleurs
class HomeController extends \JulienLinard\Core\Controller\Controller
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return $this->view('home/index', ['title' => 'Accueil']);
    }
}

$router->registerRoutes(HomeController::class);
$app->start();
```

### Intégration avec php-dotenv

`core-php` inclut automatiquement `php-dotenv`. Utilisez `loadEnv()` pour charger les variables d'environnement.

```php
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);

// Charger le fichier .env
$app->loadEnv();

// Les variables sont maintenant disponibles dans $_ENV
echo $_ENV['DB_HOST'];
```

### Intégration avec php-validator

`core-php` inclut automatiquement `php-validator`. La classe `Core\Form\Validator` utilise `php-validator` en interne, offrant des fonctionnalités de validation avancées tout en maintenant la compatibilité rétroactive.

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();

// Utiliser les fonctionnalités avancées
$validator->setCustomMessages(['email.email' => 'Email invalide']);
$validator->setLocale('fr');
$validator->setSanitize(true);

// Valider avec des règles
$result = $validator->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:8'
]);

// Accéder à l'instance php-validator sous-jacente pour les fonctionnalités avancées
$phpValidator = $validator->getPhpValidator();
$phpValidator->registerRule(new CustomRule());
```

### Intégration avec php-cache

`core-php` inclut automatiquement `php-cache`. Le système de cache est disponible via la classe `Cache`.

```php
use JulienLinard\Core\Application;
use JulienLinard\Cache\Cache;

$app = Application::create(__DIR__);

// Initialiser le cache (optionnel, peut être fait dans la configuration)
Cache::init([
    'default' => 'file',
    'drivers' => [
        'file' => [
            'path' => __DIR__ . '/cache',
            'prefix' => 'app',
            'ttl' => 3600,
        ],
    ],
]);

// Utiliser le cache dans vos contrôleurs
class ProductController extends \JulienLinard\Core\Controller\Controller
{
    #[Route(path: '/products/{id}', methods: ['GET'], name: 'product.show')]
    public function show(int $id): Response
    {
        // Récupérer depuis le cache
        $product = Cache::get("product_{$id}");
        
        if (!$product) {
            // Charger depuis la base de données
            $product = $this->loadProductFromDatabase($id);
            
            // Mettre en cache avec tags
            Cache::tags(['products', "product_{$id}"])->set("product_{$id}", $product, 3600);
        }
        
        return $this->view('product/show', ['product' => $product]);
    }
    
    #[Route(path: '/products/{id}', methods: ['DELETE'], name: 'product.delete')]
    public function delete(int $id): Response
    {
        // Supprimer le produit
        $this->deleteProductFromDatabase($id);
        
        // Invalider le cache
        Cache::tags(["product_{$id}"])->flush();
        
        return $this->json(['success' => true]);
    }
}
```

### Intégration avec doctrine-php

Utilisez `doctrine-php` pour gérer vos entités dans vos contrôleurs.

```php
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

class UserController extends Controller
{
    public function __construct(
        private EntityManager $em
    ) {}
    
    #[Route(path: '/users/{id}', methods: ['GET'], name: 'user.show')]
    public function show(int $id): Response
    {
        $user = $this->em->getRepository(User::class)->find($id);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }
        
        return $this->view('user/show', ['user' => $user]);
    }
}
```

### Intégration avec auth-php

Utilisez `auth-php` pour gérer l'authentification dans vos contrôleurs.

```php
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Auth\AuthManager;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AuthManager $auth
    ) {}
    
    #[Route(path: '/dashboard', methods: ['GET'], name: 'dashboard')]
    public function index(): Response
    {
        if (!$this->auth->check()) {
            return $this->redirect('/login');
        }
        
        $user = $this->auth->user();
        return $this->view('dashboard/index', ['user' => $user]);
    }
}
```

### Utilisation indépendante des composants

Vous pouvez utiliser les composants de `core-php` indépendamment sans `Application`.

#### Session standalone

```php
use JulienLinard\Core\Session\Session;

// Définir une valeur
Session::set('user_id', 123);

// Récupérer une valeur
$userId = Session::get('user_id');

// Flash message
Session::flash('success', 'Opération réussie');

// Supprimer
Session::remove('user_id');
```

#### Container standalone

```php
use JulienLinard\Core\Container\Container;

$container = new Container();

// Binding simple
$container->bind('database', function() {
    return new Database();
});

// Singleton
$container->singleton('logger', function() {
    return new Logger();
});

// Résolution automatique
$service = $container->make(MyService::class);
```

#### View standalone

```php
use JulienLinard\Core\View\View;

// Vue complète avec layout
$view = new View('home/index');
$view->render(['title' => 'Accueil']);

// Vue partielle (sans layout)
$view = new View('partials/header', false);
$view->render();
```

#### Form standalone

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();

// Valider avec des règles
$result = $validator->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:8'
]);

if ($result->hasErrors()) {
    // Gérer les erreurs
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
}
```

## 📚 Référence API

### Application

#### `create(string $basePath): self`

Crée une nouvelle instance de l'application.

```php
$app = Application::create(__DIR__);
```

#### `getInstance(): ?self`

Retourne l'instance existante ou null.

```php
$app = Application::getInstance();
```

#### `getInstanceOrCreate(?string $basePath = null): self`

Retourne l'instance existante ou la crée si elle n'existe pas.

```php
$app = Application::getInstanceOrCreate(__DIR__);
```

#### `getInstanceOrFail(): self`

Retourne l'instance existante ou lance une exception.

```php
$app = Application::getInstanceOrFail();
```

#### `loadEnv(string $file = '.env'): self`

Charge les variables d'environnement depuis un fichier `.env`.

```php
$app->loadEnv();
$app->loadEnv('.env.local');
```

#### `setViewsPath(string $path): self`

Définit le chemin des vues.

```php
$app->setViewsPath(__DIR__ . '/views');
```

#### `setPartialsPath(string $path): self`

Définit le chemin des partials.

```php
$app->setPartialsPath(__DIR__ . '/views/_templates');
```

#### `getRouter(): Router`

Retourne l'instance du router.

```php
$router = $app->getRouter();
```

#### `start(): void`

Démarre l'application (démarre la session).

```php
$app->start();
```

#### `handle(): void`

Traite une requête HTTP et envoie la réponse.

```php
$app->handle();
```

### Controller

#### `view(string $template, array $data = []): Response`

Rend une vue avec les données.

```php
return $this->view('home/index', ['title' => 'Accueil']);
```

#### `json(array $data, int $statusCode = 200): Response`

Retourne une réponse JSON.

```php
return $this->json(['message' => 'Success'], 200);
```

#### `redirect(string $url, int $statusCode = 302): Response`

Redirige vers une URL.

```php
return $this->redirect('/login');
```

#### `back(): Response`

Redirige vers la page précédente (si disponible).

```php
return $this->back();
```

**Note importante** : Toutes les méthodes du Controller (`view()`, `redirect()`, `json()`, `back()`) retournent maintenant une `Response` au lieu d'appeler `exit()`. Cela permet le chaînage de middlewares et facilite les tests.

### Gestion d'Erreurs

Le framework inclut un système de gestion d'erreurs amélioré avec logging et pages d'erreur personnalisables.

#### ErrorHandler

```php
use JulienLinard\Core\ErrorHandler;
use JulienLinard\Core\Exceptions\NotFoundException;
use JulienLinard\Core\Exceptions\ValidationException;

// L'ErrorHandler est automatiquement utilisé par Application
$app = Application::create(__DIR__);

// Personnaliser l'ErrorHandler
$errorHandler = new ErrorHandler($app, $logger, $debug, $viewsPath);
$app->setErrorHandler($errorHandler);
```

#### Exceptions personnalisées

```php
// NotFoundException (404)
throw new NotFoundException('Utilisateur non trouvé');

// ValidationException (422)
throw new ValidationException('Erreur de validation', [
    'email' => 'Email invalide',
    'password' => 'Mot de passe trop court'
]);
```

#### Pages d'erreur personnalisables

Créez des vues dans `views/errors/` pour personnaliser les pages d'erreur :

- `views/errors/404.html.php` - Page 404
- `views/errors/422.html.php` - Page de validation
- `views/errors/500.html.php` - Page d'erreur serveur

```php
<!-- views/errors/404.html.php -->
<h1><?= htmlspecialchars($title) ?></h1>
<p><?= htmlspecialchars($message) ?></p>
```

### Système d'Événements

Le framework inclut un système d'événements (EventDispatcher) pour l'extensibilité.

#### Utilisation

```php
use JulienLinard\Core\Application;
use JulienLinard\Core\Events\EventDispatcher;

$app = Application::create(__DIR__);
$events = $app->getEvents();

// Écouter un événement
$events->listen('request.started', function(array $data) {
    $request = $data['request'];
    // Log la requête, etc.
});

$events->listen('exception.thrown', function(array $data) {
    $exception = $data['exception'];
    // Envoyer une notification, etc.
});

// Déclencher un événement personnalisé
$events->dispatch('user.created', ['user' => $user]);
```

#### Événements intégrés

- `request.started` : Déclenché au début du traitement d'une requête
- `response.created` : Déclenché après la création de la réponse
- `response.sent` : Déclenché après l'envoi de la réponse
- `exception.thrown` : Déclenché lorsqu'une exception est levée

### Protection CSRF

Le framework inclut un middleware CSRF pour protéger vos formulaires.

#### Utilisation du middleware CSRF

```php
use JulienLinard\Core\Middleware\CsrfMiddleware;
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// Ajouter le middleware CSRF globalement
$router->addMiddleware(new CsrfMiddleware());
```

#### Helpers CSRF dans les vues

```php
use JulienLinard\Core\View\ViewHelper;

// Dans vos formulaires
<form method="POST">
    <?= ViewHelper::csrfField() ?>
    <!-- autres champs -->
</form>

// Ou récupérer juste le token
$token = ViewHelper::csrfToken();
```

#### Configuration CSRF

```php
// Personnaliser le nom du champ et la clé de session
$csrf = new CsrfMiddleware(
    tokenName: '_csrf_token',  // Nom du champ dans le formulaire
    sessionKey: '_csrf_token'  // Clé dans la session
);
```

Le middleware CSRF :
- Génère automatiquement un token pour les requêtes GET
- Valide le token pour POST, PUT, PATCH, DELETE
- Accepte le token via POST data ou header `X-CSRF-TOKEN`
- Génère un nouveau token après chaque validation

### ViewHelper - Helpers pour les vues

```php
use JulienLinard\Core\View\ViewHelper;

// Échapper du HTML
echo ViewHelper::escape($userInput);
echo ViewHelper::e($userInput); // Alias court

// Formater une date
echo ViewHelper::date($date, 'd/m/Y H:i');

// Formater un nombre
echo ViewHelper::number(1234.56, 2); // "1 234,56"

// Formater un prix
echo ViewHelper::price(99.99); // "99,99 €"

// Tronquer une chaîne
echo ViewHelper::truncate($longText, 100);

// Token CSRF
echo ViewHelper::csrfToken();
echo ViewHelper::csrfField();

// Générer une URL depuis le nom d'une route
$url = ViewHelper::route('user.show', ['id' => 123]);
$url = ViewHelper::route('users.index', [], ['page' => 2]); // Avec query params
```

## 📝 License

MIT License - Voir le fichier LICENSE pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

## 💝 Support

Si ce package vous est utile, envisagez de [devenir un sponsor](https://github.com/sponsors/julien-lin) pour soutenir le développement et la maintenance de ce projet open source.

---

**Développé avec ❤️ par Julien Linard**

