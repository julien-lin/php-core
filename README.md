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
- ✅ **Views** - Template engine with layouts + file-based view cache
- ✅ **Models** - Base Model class with hydration
- ✅ **Forms** - Form validation and error handling (powered by php-validator)
- ✅ **Session** - Session management with flash messages
- ✅ **Cache** - Integrated caching system (php-cache)
- ✅ **Middleware** - Integrated middleware system
- ✅ **Security** - CSRF + Rate Limiting + Security Headers middleware
- ✅ **Performance** - Response Compression (gzip) middleware
- ✅ **Logging** - Automatic log rotation with compression
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

// Enable view cache (directory created automatically)
View::configureCache(__DIR__.'/storage/view-cache', 300); // 5 min TTL
View::setCacheEnabled(true);

// Later clear expired cache files (older than 1 hour)
$deleted = View::clearCache(3600);
```

#### View Caching Explained

- Cache key includes: view name, full/partial flag, data hash, mtimes of view + partials.
- Automatic invalidation if: TTL exceeded OR source view/partials modified.
- Disable by calling: `View::setCacheEnabled(false)` or `View::configureCache(null)`.
- Safe writes using file locking (avoids race conditions).
- Useful for expensive templates (loops, heavy formatting, large partials).
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

`core-php` includes `php-validator` for advanced form validation with custom rules, multilingual messages, and sanitization.

#### Using the validate() method (Recommended)

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();
$result = $validator->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:8|max:255',
    'age' => 'required|numeric|min:18'
]);

if ($result->hasErrors()) {
    // Get all errors
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
    
    // Get errors for a specific field
    $emailErrors = $result->getErrorsForField('email');
} else {
    // Validation successful
}
```

#### Advanced features

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();

// Custom error messages
$validator->setCustomMessages([
    'email.email' => 'Please enter a valid email address',
    'password.min' => 'Password must be at least 8 characters'
]);

// Set locale for multilingual messages
$validator->setLocale('en');

// Enable/disable automatic sanitization
$validator->setSanitize(true);

// Register custom validation rules
$validator->registerRule(new CustomRule());

// Validate
$result = $validator->validate($data, $rules);
```

#### Manual validation (Legacy method)

```php
use JulienLinard\Core\Form\FormResult;
use JulienLinard\Core\Form\FormError;
use JulienLinard\Core\Form\FormSuccess;
use JulienLinard\Core\Form\Validator;

$formResult = new FormResult();
$validator = new Validator();

// Manual validation
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

### Integration with php-validator

`core-php` automatically includes `php-validator`. The `Core\Form\Validator` class uses `php-validator` internally, providing advanced validation features while maintaining backward compatibility.

```php
use JulienLinard\Core\Form\Validator;

$validator = new Validator();

// Use advanced features
$validator->setCustomMessages(['email.email' => 'Invalid email']);
$validator->setLocale('en');
$validator->setSanitize(true);

// Validate with rules
$result = $validator->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:8'
]);

// Access the underlying php-validator instance for advanced features
$phpValidator = $validator->getPhpValidator();
$phpValidator->registerRule(new CustomRule());
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

### View-Level Caching (Built-in)

The template engine has its own lightweight file cache focused on pure rendered HTML. Use it for fragment/page caching even if you already use `php-cache` for data.

```php
use JulienLinard\Core\View\View;

// Configure (enables automatically if directory provided)
View::configureCache(__DIR__.'/storage/view-cache', 600); // 10 min

// Optionally toggle
View::setCacheEnabled(true);

// Render (cached transparently)
echo (new View('home/index'))->render(['title' => 'Hello']);

// Clear expired entries (max age 0 = all)
View::clearCache(0);
```

When to use which:
- Use `php-cache` for data (arrays, objects, API results).
- Use view cache for fully rendered HTML sections/pages.

### Security Middleware

#### Rate Limiting Middleware

Protect endpoints against brute force or abusive traffic.

```php
use JulienLinard\Core\Middleware\RateLimitMiddleware;
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// 100 requests / 60s per IP (file storage)
$router->addMiddleware(new RateLimitMiddleware(100, 60, __DIR__.'/storage/ratelimit'));
```

Behavior:
- Tracks counts per IP + route path.
- Returns HTTP 429 with simple body when exceeded.
- Window resets automatically after configured seconds.
- Storage strategy: flat file (extendable to memory/Redis in future versions).

Recommended values:
- Login: 10 / 60s
- Generic API: 100 / 60s
- Expensive endpoints: 20 / 300s

#### Security Headers Middleware

Add security HTTP headers to protect against common attacks (XSS, clickjacking, MIME sniffing, etc.).

```php
use JulienLinard\Core\Middleware\SecurityHeadersMiddleware;
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// Default configuration (good security defaults)
$router->addMiddleware(new SecurityHeadersMiddleware());

// Custom configuration
$router->addMiddleware(new SecurityHeadersMiddleware([
    'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'",
    'hsts' => 'max-age=31536000; includeSubDomains',
    'xFrameOptions' => 'DENY',
    'referrerPolicy' => 'strict-origin-when-cross-origin',
]));
```

Headers included:
- **Content-Security-Policy (CSP)** - Prevents XSS attacks
- **Strict-Transport-Security (HSTS)** - Forces HTTPS (only in HTTPS mode)
- **X-Frame-Options** - Prevents clickjacking (DENY, SAMEORIGIN)
- **X-Content-Type-Options** - Prevents MIME sniffing (nosniff)
- **Referrer-Policy** - Controls referrer information
- **Permissions-Policy** - Controls browser features
- **X-XSS-Protection** - Legacy XSS protection for older browsers

The middleware automatically detects HTTPS via `HTTPS`, `X-Forwarded-Proto`, or port 443.

### Performance Middleware

#### Compression Middleware

Automatically compress HTTP responses with gzip to reduce bandwidth usage.

```php
use JulienLinard\Core\Middleware\CompressionMiddleware;
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// Default configuration (compresses responses > 1KB)
$router->addMiddleware(new CompressionMiddleware());

// Custom configuration
$router->addMiddleware(new CompressionMiddleware([
    'level' => 6,        // Compression level (1-9, default: 6)
    'minSize' => 1024,   // Minimum size to compress in bytes (default: 1024)
    'contentTypes' => [  // MIME types to compress
        'text/html',
        'application/json',
        'text/css',
    ],
]));
```

Features:
- Automatic gzip compression based on `Accept-Encoding` header
- Configurable compression level (1-9)
- Minimum size threshold to avoid compressing small responses
- Content-Type filtering (only compresses specified MIME types
- Adds `Content-Encoding: gzip` and `Vary: Accept-Encoding` headers automatically

### Logging with Rotation

The `SimpleLogger` now supports automatic log rotation to prevent disk space issues.

```php
use JulienLinard\Core\Logging\SimpleLogger;

// Default configuration (10MB max, 5 files, compressed)
$logger = new SimpleLogger('/var/log/app.log', 'info');

// Custom rotation configuration
$logger = new SimpleLogger('/var/log/app.log', 'info', [
    'maxSize' => 10 * 1024 * 1024,  // 10MB (default)
    'maxFiles' => 5,                 // Keep 5 archived files (default)
    'compress' => true,              // Compress archives (default: true)
]);

// Log messages (rotation happens automatically when maxSize is reached)
$logger->info('Application started');
$logger->error('An error occurred', ['context' => 'value']);

// Force rotation manually
$logger->rotateNow();

// Get/update rotation configuration
$config = $logger->getRotationConfig();
$logger->setRotationConfig(['maxFiles' => 10]);
```

Features:
- **Automatic rotation** when file size exceeds `maxSize`
- **File archiving** with numbered files (`app.1.log.gz`, `app.2.log.gz`, etc.)
- **Compression** of archived files (optional, saves disk space)
- **Automatic cleanup** of old files beyond `maxFiles`
- **Configurable log levels** (string: 'debug', 'info', etc. or int: 0-7)
- **Manual rotation** via `rotateNow()` method

Rotation behavior:
- When `app.log` exceeds `maxSize`, it's archived as `app.1.log.gz`
- Existing archives are shifted (`app.1.log.gz` → `app.2.log.gz`)
- Old files beyond `maxFiles` are automatically deleted
- The current log file is cleared and logging continues

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
use JulienLinard\Core\Form\Validator;

$validator = new Validator();

// Validate with rules
$result = $validator->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:8'
]);

if ($result->hasErrors()) {
    // Handle errors
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
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

#### `back(): Response`

Redirects to the previous page (if available).

```php
return $this->back();
```

**Important Note**: All Controller methods (`view()`, `redirect()`, `json()`, `back()`) now return a `Response` instead of calling `exit()`. This allows middleware chaining and makes testing easier.

### Error Handling

The framework includes an improved error handling system with logging and customizable error pages.

#### ErrorHandler

```php
use JulienLinard\Core\ErrorHandler;
use JulienLinard\Core\Exceptions\NotFoundException;
use JulienLinard\Core\Exceptions\ValidationException;

// ErrorHandler is automatically used by Application
$app = Application::create(__DIR__);

// Customize ErrorHandler
$errorHandler = new ErrorHandler($app, $logger, $debug, $viewsPath);
$app->setErrorHandler($errorHandler);
```

#### Custom Exceptions

```php
// NotFoundException (404)
throw new NotFoundException('User not found');

// ValidationException (422)
throw new ValidationException('Validation error', [
    'email' => 'Invalid email',
    'password' => 'Password too short'
]);
```

#### Customizable Error Pages

Create views in `views/errors/` to customize error pages:

- `views/errors/404.html.php` - 404 page
- `views/errors/422.html.php` - Validation page
- `views/errors/500.html.php` - Server error page

```php
<!-- views/errors/404.html.php -->
<h1><?= htmlspecialchars($title) ?></h1>
<p><?= htmlspecialchars($message) ?></p>
```

### Event System

The framework includes an event system (EventDispatcher) for extensibility.

#### Usage

```php
use JulienLinard\Core\Application;
use JulienLinard\Core\Events\EventDispatcher;

$app = Application::create(__DIR__);
$events = $app->getEvents();

// Listen to an event
$events->listen('request.started', function(array $data) {
    $request = $data['request'];
    // Log the request, etc.
});

$events->listen('exception.thrown', function(array $data) {
    $exception = $data['exception'];
    // Send notification, etc.
});

// Dispatch a custom event
$events->dispatch('user.created', ['user' => $user]);
```

#### Built-in Events

- `request.started` : Dispatched at the start of request processing
- `response.created` : Dispatched after response creation
- `response.sent` : Dispatched after response is sent
- `exception.thrown` : Dispatched when an exception is thrown

### Centralized Configuration

The framework allows loading configuration from PHP files in a `config/` directory.

```php
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);

// Load configuration from config/
$app->loadConfig('config');

// Files config/app.php, config/database.php, etc. are automatically loaded
// Accessible via $app->getConfig()->get('app.name')
```

**Recommended structure** :
```
config/
  app.php      # Application configuration
  database.php # Database configuration
  cache.php    # Cache configuration
```

**Example config/app.php** :
```php
<?php
return [
    'name' => 'My Application',
    'debug' => true,
    'timezone' => 'Europe/Paris',
];
```

### CSRF Protection

The framework includes a CSRF middleware to protect your forms.

#### Using CSRF Middleware

```php
use JulienLinard\Core\Middleware\CsrfMiddleware;
use JulienLinard\Core\Application;

$app = Application::create(__DIR__);
$router = $app->getRouter();

// Add CSRF middleware globally
$router->addMiddleware(new CsrfMiddleware());
```

#### CSRF Helpers in Views

```php
use JulienLinard\Core\View\ViewHelper;

// In your forms
<form method="POST">
    <?= ViewHelper::csrfField() ?>
    <!-- other fields -->
</form>

// Or get just the token
$token = ViewHelper::csrfToken();
```

#### CSRF Configuration

```php
// Customize field name and session key
$csrf = new CsrfMiddleware(
    tokenName: '_csrf_token',  // Field name in form
    sessionKey: '_csrf_token'  // Session key
);
```

The CSRF middleware:
- Automatically generates a token for GET requests
- Validates the token for POST, PUT, PATCH, DELETE
- Accepts token via POST data or `X-CSRF-TOKEN` header
- Generates a new token after each validation

### ViewHelper - View Helpers

```php
use JulienLinard\Core\View\ViewHelper;

// Escape HTML
echo ViewHelper::escape($userInput);
echo ViewHelper::e($userInput); // Short alias

// Format a date
echo ViewHelper::date($date, 'd/m/Y H:i');

// Format a number
echo ViewHelper::number(1234.56, 2); // "1,234.56"

// Format a price
echo ViewHelper::price(99.99); // "99.99 €"

// Truncate a string
echo ViewHelper::truncate($longText, 100);

// CSRF token
echo ViewHelper::csrfToken();
echo ViewHelper::csrfField();

// Generate URL from route name
$url = ViewHelper::route('user.show', ['id' => 123]);
$url = ViewHelper::route('users.index', [], ['page' => 2]); // With query params
```

## 📝 License

MIT License - See the LICENSE file for more details.

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or a pull request.

## 💝 Support

If this package is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

**Developed with ❤️ by Julien Linard**

