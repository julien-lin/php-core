# Core PHP - Framework MVC Complet

Un framework MVC moderne et complet pour PHP 8+ avec Container DI, Controllers, Views, Forms, Session et plus.

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
- ✅ **Forms** - Validation de formulaires et gestion d'erreurs
- ✅ **Session** - Gestion des sessions avec flash messages
- ✅ **Middleware** - Système de middlewares intégré
- ✅ **Config** - Gestion de la configuration
- ✅ **Exceptions** - Gestion centralisée des erreurs

## 📖 Documentation

### Application

```php
use JulienLinard\Core\Application;

// Créer une instance de l'application
$app = Application::create(__DIR__);

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

```php
use JulienLinard\Core\Form\FormResult;
use JulienLinard\Core\Form\FormError;
use JulienLinard\Core\Form\FormSuccess;
use JulienLinard\Core\Form\Validator;

$formResult = new FormResult();

// Validation
$validator = new Validator();
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

## 🔗 Intégration avec php-router

```php
use JulienLinard\Router\Router;
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);
$router = $app->getRouter();

$router->registerRoutes(HomeController::class);
$router->registerRoutes(UserController::class);

$app->start();
```

## 📝 License

MIT License - Voir le fichier LICENSE pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

---

**Développé avec ❤️ par Julien Linard**

