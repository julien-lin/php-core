# Core PHP - Complete MVC Framework

[🇫🇷 Read in French](README.fr.md) | [🇬🇧 Read in English](README.md)

## 💝 Support the project

If this package is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

A modern and complete MVC framework for PHP 8+ with Container DI, Controllers, Views, Forms, Session, Cache and more.

## 🚀 Installation

```bash
composer require julienlinard/core-php
```

**Requirements**: PHP 8.0 or higher

## ⚡ Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Core\Application;
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Core\View\View;

// Bootstrap the application
$app = Application::create(__DIR__);
$app->start();
```

## 📋 Features

- ✅ **Application** - Main framework class
- ✅ **Container DI** - Dependency injection with auto-wiring
- ✅ **Controllers** - Base class with utility methods
- ✅ **Views** - Template engine with layouts
- ✅ **Models** - Base Model class with hydration
- ✅ **Forms** - Form validation and error handling
- ✅ **Session** - Session management with flash messages
- ✅ **Cache** - Integrated caching system (php-cache)
- ✅ **Middleware** - Integrated middleware system
- ✅ **Config** - Configuration management
- ✅ **Exceptions** - Centralized error handling

## 📖 Documentation

### Application

```php
use JulienLinard\Core\Application;

// Create an application instance
$app = Application::create(__DIR__);

// Get existing instance (may return null)
$app = Application::getInstance();

// Get instance or create it if it doesn't exist (useful for error handlers)
$app = Application::getInstanceOrCreate(__DIR__);

// Get instance or throw exception if it doesn't exist
$app = Application::getInstanceOrFail();

// Configure view paths
$app->setViewsPath(__DIR__ . '/views');
$app->setPartialsPath(__DIR__ . '/views/_templates');

// Start the application
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
            'title' => 'Home',
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

// Full view with layout
$view = new View('home/index');
$view->render(['title' => 'Home']);

// Partial view (without layout)
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

// Automatic hydration
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
    $formResult->addError(new FormError('Email required'));
}

if (!$validator->email($data['email'])) {
    $formResult->addError(new FormError('Invalid email'));
}

if ($formResult->hasErrors()) {
    // Handle errors
} else {
    $formResult->addSuccess(new FormSuccess('Form validated'));
}
```

### Session

```php
use JulienLinard\Core\Session\Session;

// Set a value
Session::set('user_id', 123);

// Get a value
$userId = Session::get('user_id');

// Flash message
Session::flash('success', 'Operation successful');

// Remove
Session::remove('user_id');
```

### DI Container

```php
use JulienLinard\Core\Container\Container;

$container = new Container();

// Simple binding
$container->bind('database', function() {
    return new Database();
});

// Singleton
$container->singleton('logger', function() {
    return new Logger();
});

// Automatic resolution
$service = $container->make(MyService::class);
```

## 🔗 Integration with other packages

### Integration with php-router

`core-php` automatically includes `php-router`. The router is accessible via `getRouter()`.

```php
use JulienLinard\Core\Application;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// Define routes in your controllers
class HomeController extends \JulienLinard\Core\Controller\Controller
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return $this->view('home/index', ['title' => 'Home']);
    }
}

$router->registerRoutes(HomeController::class);
$app->start();
```

### Integration with php-dotenv

`core-php` automatically includes `php-dotenv`. Use `loadEnv()` to load environment variables.

```php
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);

// Load .env file
$app->loadEnv();

// Variables are now available in $_ENV
echo $_ENV['DB_HOST'];
```

### Integration with php-cache

`core-php` automatically includes `php-cache`. The caching system is available via the `Cache` class.

```php
use JulienLinard\Core\Application;
use JulienLinard\Cache\Cache;

$app = Application::create(__DIR__);

// Initialize cache (optional, can be done in configuration)
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

// Use cache in your controllers
class ProductController extends \JulienLinard\Core\Controller\Controller
{
    #[Route(path: '/products/{id}', methods: ['GET'], name: 'product.show')]
    public function show(int $id): Response
    {
        // Get from cache
        $product = Cache::get("product_{$id}");
        
        if (!$product) {
            // Load from database
            $product = $this->loadProductFromDatabase($id);
            
            // Cache with tags
            Cache::tags(['products', "product_{$id}"])->set("product_{$id}", $product, 3600);
        }
        
        return $this->view('product/show', ['product' => $product]);
    }
    
    #[Route(path: '/products/{id}', methods: ['DELETE'], name: 'product.delete')]
    public function delete(int $id): Response
    {
        // Delete product
        $this->deleteProductFromDatabase($id);
        
        // Invalidate cache
        Cache::tags(["product_{$id}"])->flush();
        
        return $this->json(['success' => true]);
    }
}
```

### Integration with doctrine-php

Use `doctrine-php` to manage your entities in your controllers.

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

### Integration with auth-php

Use `auth-php` to manage authentication in your controllers.

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

### Standalone component usage

You can use `core-php` components independently without `Application`.

#### Session standalone

```php
use JulienLinard\Core\Session\Session;

// Set a value
Session::set('user_id', 123);

// Get a value
$userId = Session::get('user_id');

// Flash message
Session::flash('success', 'Operation successful');

// Remove
Session::remove('user_id');
```

#### Container standalone

```php
use JulienLinard\Core\Container\Container;

$container = new Container();

// Simple binding
$container->bind('database', function() {
    return new Database();
});

// Singleton
$container->singleton('logger', function() {
    return new Logger();
});

// Automatic resolution
$service = $container->make(MyService::class);
```

#### View standalone

```php
use JulienLinard\Core\View\View;

// Full view with layout
$view = new View('home/index');
$view->render(['title' => 'Home']);

// Partial view (without layout)
$view = new View('partials/header', false);
$view->render();
```

#### Form standalone

```php
use JulienLinard\Core\Form\FormResult;
use JulienLinard\Core\Form\FormError;
use JulienLinard\Core\Form\FormSuccess;
use JulienLinard\Core\Form\Validator;

$formResult = new FormResult();
$validator = new Validator();

// Validation
if (!$validator->required($data['email'])) {
    $formResult->addError(new FormError('Email required'));
}

if (!$validator->email($data['email'])) {
    $formResult->addError(new FormError('Invalid email'));
}

if ($formResult->hasErrors()) {
    // Handle errors
} else {
    $formResult->addSuccess(new FormSuccess('Form validated'));
}
```

## 📚 API Reference

### Application

#### `create(string $basePath): self`

Creates a new application instance.

```php
$app = Application::create(__DIR__);
```

#### `getInstance(): ?self`

Returns the existing instance or null.

```php
$app = Application::getInstance();
```

#### `getInstanceOrCreate(?string $basePath = null): self`

Returns the existing instance or creates it if it doesn't exist.

```php
$app = Application::getInstanceOrCreate(__DIR__);
```

#### `getInstanceOrFail(): self`

Returns the existing instance or throws an exception.

```php
$app = Application::getInstanceOrFail();
```

#### `loadEnv(string $file = '.env'): self`

Loads environment variables from a `.env` file.

```php
$app->loadEnv();
$app->loadEnv('.env.local');
```

#### `setViewsPath(string $path): self`

Sets the views path.

```php
$app->setViewsPath(__DIR__ . '/views');
```

#### `setPartialsPath(string $path): self`

Sets the partials path.

```php
$app->setPartialsPath(__DIR__ . '/views/_templates');
```

#### `getRouter(): Router`

Returns the router instance.

```php
$router = $app->getRouter();
```

#### `start(): void`

Starts the application (starts the session).

```php
$app->start();
```

#### `handle(): void`

Processes an HTTP request and sends the response.

```php
$app->handle();
```

### Controller

#### `view(string $template, array $data = []): Response`

Renders a view with data.

```php
return $this->view('home/index', ['title' => 'Home']);
```

#### `json(array $data, int $statusCode = 200): Response`

Returns a JSON response.

```php
return $this->json(['message' => 'Success'], 200);
```

#### `redirect(string $url, int $statusCode = 302): Response`

Redirects to a URL.

```php
return $this->redirect('/login');
```

## 📝 License

MIT License - See the LICENSE file for more details.

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or a pull request.

## 💝 Support

If this package is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

**Developed with ❤️ by Julien Linard**

