# Architecture du Module core-php

**Version** : 1.4.4+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le module `core-php` est un framework MVC complet pour applications PHP. Il fournit un conteneur d'injection de dépendances, un système de routing, des contrôleurs, des vues, des middlewares, et des fonctionnalités de sécurité intégrées.

---

## Architecture Globale

```
┌─────────────────────────────────────────────────────────────┐
│                      Application                            │
│  (Singleton Pattern - Point d'entrée principal)           │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┴──────────────┐
        │                             │
┌───────▼────────┐          ┌────────▼──────────┐
│    Router      │          │    Container      │
│  (php-router)  │          │  (DI Container)   │
└───────┬────────┘          └────────┬──────────┘
        │                             │
        │                    ┌────────▼──────────┐
        │                    │  Auto-wiring      │
        │                    │  Reflection      │
        │                    └────────────────────┘
        │
┌───────▼────────┐
│  Middlewares   │
│  (Chain)       │
└───────┬────────┘
        │
┌───────▼────────┐
│  Controller    │
│  (MVC)         │
└───────┬────────┘
        │
┌───────▼────────┐
│     View       │
│  (Templates)   │
└────────────────┘
```

---

## Composants Principaux

### 1. Application

**Rôle** : Point d'entrée principal du framework  
**Pattern** : Singleton Pattern  
**Responsabilités** :
- Initialisation du framework
- Gestion du cycle de vie de l'application
- Configuration des sessions sécurisées
- Gestion des erreurs
- Dispatch d'événements

**Fichier** : `src/Core/Application.php`

**Méthodes principales** :
- `create(string $basePath): self` : Crée l'instance singleton
- `handle(): void` : Traite une requête HTTP
- `start(): void` : Initialise l'application
- `shutdown(): void` : Nettoie les ressources

**Exemple** :
```php
$app = Application::create(__DIR__);
$app->handle();
```

### 2. Container (Injection de Dépendances)

**Rôle** : Conteneur d'injection de dépendances avec auto-wiring  
**Pattern** : Service Locator / Dependency Injection  
**Responsabilités** :
- Résolution automatique des dépendances
- Gestion des singletons
- Cache de requête pour optimiser les performances
- Protection contre les dépendances circulaires

**Fichier** : `src/Core/Container/Container.php`

**Fonctionnalités** :
- Auto-wiring via Reflection
- Bindings personnalisés
- Singletons
- Cache de requête (instances non-singleton)
- Détection de dépendances circulaires
- Limite de profondeur de résolution (20 niveaux)

**Exemple** :
```php
$container = $app->getContainer();

// Enregistrer un singleton
$container->singleton(MyService::class, fn() => new MyService());

// Résoudre une classe
$service = $container->make(MyService::class);
```

### 3. Router

**Rôle** : Gestion du routing HTTP  
**Pattern** : Front Controller  
**Dépendance** : `julienlinard/php-router`

**Fonctionnalités** :
- Routes nommées
- Paramètres dynamiques
- Middlewares
- Injection de dépendances dans les contrôleurs

**Exemple** :
```php
$router = $app->getRouter();
$router->get('/user/{id}', [UserController::class, 'show']);
```

### 4. Controller

**Rôle** : Gestion de la logique métier  
**Pattern** : MVC Controller  
**Responsabilités** :
- Traitement des requêtes
- Validation des données
- Retour de réponses (vues, JSON, redirections)

**Fichier** : `src/Core/Controller/Controller.php`

**Méthodes principales** :
- `view(string $name, array $data, bool $complete): Response`
- `redirect(string $uri, int $status): Response`
- `json(mixed $data, int $status): Response`

**Exemple** :
```php
class UserController extends Controller
{
    public function show(Request $request): Response
    {
        $user = User::find($request->getPathParam('id'));
        return $this->view('user/show', ['user' => $user]);
    }
}
```

### 5. View

**Rôle** : Moteur de templates  
**Pattern** : Template View  
**Responsabilités** :
- Rendu des templates PHP
- Cache des vues compilées
- Gestion des partials (header/footer)
- Support optionnel de Vision (syntaxe `{{ }}`)

**Fichier** : `src/Core/View/View.php`

**Fonctionnalités** :
- Cache avec TTL configurable
- Invalidation automatique par mtime
- Cache de métadonnées (mtimes)
- Support des vues complètes (avec layout)
- Hash sécurisé (xxh3/sha256) pour les clés de cache

**Exemple** :
```php
$view = new View('home/index', true); // true = avec layout
$content = $view->render(['title' => 'Accueil']);
```

### 6. Middleware

**Rôle** : Traitement des requêtes avant/après le contrôleur  
**Pattern** : Chain of Responsibility  
**Responsabilités** :
- Sécurité (CSRF, headers, rate limiting)
- Compression
- CORS
- Validation

**Fichiers** : `src/Core/Middleware/*.php`

**Middlewares disponibles** :
- `CsrfMiddleware` : Protection CSRF
- `SecurityHeadersMiddleware` : Headers de sécurité HTTP
- `RateLimitMiddleware` : Limitation de débit
- `CompressionMiddleware` : Compression gzip
- `CorsMiddleware` : Gestion CORS
- `RequestValidationMiddleware` : Validation des requêtes

### 7. ErrorHandler

**Rôle** : Gestion centralisée des erreurs  
**Pattern** : Exception Handler  
**Responsabilités** :
- Capture des exceptions
- Génération de pages d'erreur
- Logging sécurisé (redaction des données sensibles)
- Cache des pages d'erreur

**Fichier** : `src/Core/ErrorHandler.php`

**Fonctionnalités** :
- Pages d'erreur personnalisables
- Mode debug
- Redaction automatique des données sensibles
- Cache des pages d'erreur générées

### 8. Session

**Rôle** : Gestion des sessions PHP  
**Pattern** : Facade  
**Responsabilités** :
- Configuration sécurisée des sessions
- Régénération périodique de l'ID
- Gestion des données de session

**Fichier** : `src/Core/Session/Session.php`

**Sécurité** :
- HttpOnly cookies
- Secure cookies (HTTPS)
- SameSite=Strict
- Régénération toutes les 15 minutes
- Mode strict activé

### 9. Config

**Rôle** : Gestion de la configuration  
**Pattern** : Registry  
**Responsabilités** :
- Chargement des fichiers de configuration
- Cache des configurations
- Validation des noms de fichiers

**Fichiers** : `src/Core/Config/Config.php`, `ConfigLoader.php`

### 10. Model

**Rôle** : Modèle de données de base  
**Pattern** : Active Record (inspiré)  
**Responsabilités** :
- Protection Mass Assignment
- Gestion des attributs
- Validation basique

**Fichier** : `src/Core/Model/Model.php`

**Sécurité** :
- Protection `$fillable` / `$guarded`
- Protection par défaut du champ `id`

---

## Flux d'Exécution

### 1. Initialisation

```
Application::create($basePath)
    ├── Création du Container
    ├── Création du Router
    ├── Création du Config
    ├── Création de l'EventDispatcher
    └── Enregistrement des singletons
```

### 2. Traitement d'une Requête

```
Application::handle()
    ├── Application::start()
    │   ├── Chargement de .env
    │   ├── Configuration sessions sécurisées
    │   ├── Validation taille POST
    │   └── Régénération session si nécessaire
    │
    ├── Création de Request
    ├── Dispatch événement 'request.started'
    │
    ├── Router::handle($request)
    │   ├── Exécution des middlewares
    │   ├── Résolution de la route
    │   ├── Injection de dépendances dans le contrôleur
    │   └── Exécution de l'action du contrôleur
    │
    ├── Dispatch événement 'response.created'
    ├── Response::send()
    ├── Dispatch événement 'response.sent'
    └── Container::clearRequestCache()
```

### 3. Gestion d'Erreur

```
Exception levée
    ├── Dispatch événement 'exception.thrown'
    ├── ErrorHandler::handle($exception)
    │   ├── Logging (avec redaction)
    │   └── Génération page d'erreur
    └── Envoi de la réponse d'erreur
```

---

## Patterns de Conception Utilisés

1. **Singleton** : Application
2. **Factory** : Container (résolution de classes)
3. **Facade** : Session, View
4. **Strategy** : Middlewares (chaîne de responsabilité)
5. **Template Method** : Controller (méthodes helper)
6. **Observer** : EventDispatcher
7. **Registry** : Config

---

## Optimisations de Performance

1. **Container** :
   - Cache de ReflectionClass
   - Cache de requête (instances non-singleton)
   - Limite de profondeur de résolution

2. **View** :
   - Cache des vues compilées
   - Cache de métadonnées (mtimes)
   - Cache des chemins de fichiers
   - Cache du contenu des partials

3. **Config** :
   - Cache statique des configurations
   - Remplacement de `glob()` par `scandir()`

4. **Rate Limiting** :
   - Cache mémoire (durée de requête)
   - Sauvegarde asynchrone

5. **ErrorHandler** :
   - Cache des pages d'erreur générées

---

## Sécurité

Voir `SECURITY.md` pour les détails complets.

**Mesures principales** :
- Protection CSRF
- Headers de sécurité HTTP
- Sessions sécurisées
- Validation des redirections (Open Redirect)
- Protection Mass Assignment
- Redaction des données sensibles dans les logs
- Hash sécurisés (xxh3/sha256 au lieu de MD5)
- Rate limiting
- Validation de la taille POST

---

## Événements

Le framework utilise un EventDispatcher pour déclencher des événements :

- `request.started` : Début de traitement d'une requête
- `response.created` : Réponse créée
- `response.sent` : Réponse envoyée
- `exception.thrown` : Exception levée
- `application.shutdown` : Fin de l'application

**Exemple** :
```php
$app->getEvents()->on('request.started', function($data) {
    // Log de la requête
});
```

---

## Dépendances Externes

- `julienlinard/php-router` : Système de routing
- `julienlinard/php-dotenv` : Gestion des variables d'environnement
- `julienlinard/php-cache` : Système de cache (optionnel)
- `julienlinard/php-validator` : Validation des données
- `julienlinard/php-vision` : Moteur de template alternatif (optionnel)

---

## Structure des Fichiers

```
src/Core/
├── Application.php          # Point d'entrée
├── Container/               # Injection de dépendances
│   └── Container.php
├── Controller/              # Contrôleurs
│   ├── Controller.php
│   └── ControllerInterface.php
├── View/                    # Moteur de templates
│   ├── View.php
│   └── ViewHelper.php
├── Middleware/              # Middlewares
│   ├── CsrfMiddleware.php
│   ├── SecurityHeadersMiddleware.php
│   ├── RateLimitMiddleware.php
│   ├── CompressionMiddleware.php
│   ├── CorsMiddleware.php
│   ├── RequestValidationMiddleware.php
│   └── MiddlewareInterface.php
├── Session/                 # Gestion des sessions
│   └── Session.php
├── Config/                  # Configuration
│   ├── Config.php
│   └── ConfigLoader.php
├── Model/                   # Modèles
│   └── Model.php
├── Form/                    # Formulaires
│   ├── Validator.php
│   ├── FormResult.php
│   ├── FormSuccess.php
│   └── FormError.php
├── Logging/                 # Logging
│   ├── LoggerInterface.php
│   └── SimpleLogger.php
├── Events/                  # Événements
│   └── EventDispatcher.php
├── Exceptions/              # Exceptions
│   ├── FrameworkException.php
│   ├── NotFoundException.php
│   └── ValidationException.php
└── ErrorHandler.php         # Gestion des erreurs
```

---

## Notes Importantes

1. **Singleton** : L'Application utilise un pattern Singleton. Utiliser `Application::create()` pour créer l'instance.

2. **Auto-wiring** : Le Container résout automatiquement les dépendances via Reflection. Pas besoin de configuration pour la plupart des cas.

3. **Cache** : Le cache de requête du Container est automatiquement nettoyé après chaque requête.

4. **Sessions** : Les sessions sont configurées de manière sécurisée par défaut. L'ID est régénéré toutes les 15 minutes.

5. **Hash** : Les hash de cache utilisent xxh3 (si disponible) ou sha256, jamais MD5.

---

**Note** : Cette documentation décrit l'architecture générale. Pour plus de détails sur des composants spécifiques, voir :
- `CONTAINER.md` : Container et auto-wiring
- `MIDDLEWARES.md` : Middlewares disponibles
- `SECURITY.md` : Mesures de sécurité

