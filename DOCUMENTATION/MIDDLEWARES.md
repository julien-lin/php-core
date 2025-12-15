# Middlewares

**Version** : 1.4.4+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Les middlewares de `core-php` permettent d'intercepter et de traiter les requêtes HTTP avant qu'elles n'atteignent le contrôleur. Ils suivent le pattern Chain of Responsibility et peuvent modifier la requête, la réponse, ou arrêter l'exécution.

---

## Interface Middleware

Tous les middlewares implémentent `MiddlewareInterface` :

```php
interface MiddlewareInterface
{
    public function handle(Request $request): ?Response;
}
```

**Retour** :
- `null` : Continue la chaîne de middlewares
- `Response` : Arrête l'exécution et retourne cette réponse

---

## Middlewares Disponibles

### 1. CsrfMiddleware

**Rôle** : Protection contre les attaques CSRF (Cross-Site Request Forgery)

**Fichier** : `src/Core/Middleware/CsrfMiddleware.php`

**Fonctionnalités** :
- Génération de tokens CSRF
- Vérification des tokens pour les méthodes POST, PUT, PATCH, DELETE
- Exclusion de chemins (ex: `/api`)
- Support des tokens dans POST et headers (`X-CSRF-TOKEN`)

**Configuration** :
```php
$middleware = new CsrfMiddleware(
    tokenName: '_token',           // Nom du champ dans le formulaire
    sessionKey: '_csrf_token',     // Clé de session
    excludedPaths: ['/api']        // Chemins exclus
);
```

**Utilisation** :
```php
$router->middleware(new CsrfMiddleware());
```

**Dans les formulaires** :
```php
<form method="POST">
    <?= CsrfMiddleware::field() ?>
    <!-- Autres champs -->
</form>
```

**Récupération du token** :
```php
$token = CsrfMiddleware::getToken();
```

**Comportement** :
- Génère un nouveau token à chaque requête GET
- Vérifie le token pour POST/PUT/PATCH/DELETE
- Retourne 403 si le token est invalide

---

### 2. SecurityHeadersMiddleware

**Rôle** : Ajoute des headers de sécurité HTTP

**Fichier** : `src/Core/Middleware/SecurityHeadersMiddleware.php`

**Headers supportés** :
- `Content-Security-Policy` : Protection XSS
- `Strict-Transport-Security` : Force HTTPS
- `X-Frame-Options` : Protection clickjacking
- `X-Content-Type-Options` : Empêche le MIME sniffing
- `Referrer-Policy` : Contrôle des informations de referrer
- `Permissions-Policy` : Contrôle des fonctionnalités du navigateur
- `X-XSS-Protection` : Protection XSS (déprécié mais utile)

**Configuration** :
```php
$middleware = new SecurityHeadersMiddleware([
    'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'",
    'hsts' => 'max-age=31536000; includeSubDomains',
    'xFrameOptions' => 'SAMEORIGIN',
    'xContentTypeOptions' => 'nosniff',
    'referrerPolicy' => 'strict-origin-when-cross-origin',
    'permissionsPolicy' => 'geolocation=(), microphone=()',
    'xXssProtection' => '1; mode=block',
]);
```

**Utilisation** :
```php
$router->middleware(new SecurityHeadersMiddleware());
```

**Valeurs par défaut** :
- CSP : `"default-src 'self'"`
- X-Frame-Options : `SAMEORIGIN`
- X-Content-Type-Options : `nosniff`
- Referrer-Policy : `strict-origin-when-cross-origin`
- X-XSS-Protection : `1; mode=block`

**Note** : HSTS n'est ajouté que si la requête est en HTTPS.

---

### 3. RateLimitMiddleware

**Rôle** : Limite le nombre de requêtes par IP/route

**Fichier** : `src/Core/Middleware/RateLimitMiddleware.php`

**Fonctionnalités** :
- Limitation par IP et route
- Fenêtre de temps configurable
- Stockage fichier avec cache mémoire
- Protection contre les race conditions (flock)

**Configuration** :
```php
$middleware = new RateLimitMiddleware(
    maxRequests: 100,              // Nombre max de requêtes
    windowSeconds: 60,              // Fenêtre en secondes (60 = 1 minute)
    storagePath: '/tmp/rate-limit'  // Dossier de stockage
);
```

**Utilisation** :
```php
// 100 requêtes par minute
$router->middleware(new RateLimitMiddleware(100, 60));

// 10 requêtes par seconde
$router->middleware(new RateLimitMiddleware(10, 1));
```

**Réponse** :
- Retourne `429 Too Many Requests` si la limite est dépassée

**Optimisations** :
- Cache mémoire pour éviter les I/O fichiers
- Sauvegarde asynchrone
- Nettoyage périodique du cache (toutes les 5 minutes)

**Sécurité** :
- Utilise SHA256 pour les clés de hash (pas MD5)
- Permissions sécurisées (0750) pour les fichiers
- Verrouillage avec `flock()` pour éviter les race conditions

---

### 4. CompressionMiddleware

**Rôle** : Compresse les réponses HTTP avec gzip

**Fichier** : `src/Core/Middleware/CompressionMiddleware.php`

**Fonctionnalités** :
- Compression gzip automatique
- Filtrage par Content-Type
- Taille minimum configurable
- Niveau de compression configurable

**Configuration** :
```php
$middleware = new CompressionMiddleware([
    'level' => 6,                  // Niveau de compression (1-9)
    'minSize' => 1024,             // Taille minimum en bytes
    'contentTypes' => [             // Types MIME à compresser
        'text/html',
        'text/css',
        'application/javascript',
        'application/json',
    ],
]);
```

**Utilisation** :
```php
$router->middleware(new CompressionMiddleware());
```

**Types compressibles par défaut** :
- `text/html`
- `text/css`
- `text/javascript`
- `application/javascript`
- `application/json`
- `text/xml`
- `application/xml`

**Comportement** :
- Vérifie `Accept-Encoding: gzip` dans la requête
- Compresse seulement si la taille >= minSize
- Ajoute `Content-Encoding: gzip` et `Vary: Accept-Encoding`
- Met à jour `Content-Length`

**Méthode publique** :
```php
$compressedResponse = $middleware->compress($response);
```

---

### 5. CorsMiddleware

**Rôle** : Gestion des requêtes CORS (Cross-Origin Resource Sharing)

**Fichier** : `src/Core/Middleware/CorsMiddleware.php`

**Fonctionnalités** :
- Configuration des origines autorisées
- Support des méthodes HTTP
- Support des headers personnalisés
- Gestion des requêtes preflight (OPTIONS)

**Configuration** :
```php
$middleware = new CorsMiddleware([
    'allowedOrigins' => ['https://example.com', 'https://app.example.com'],
    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowedHeaders' => ['Content-Type', 'Authorization'],
    'exposedHeaders' => ['X-Custom-Header'],
    'maxAge' => 3600,
    'allowCredentials' => true,
]);
```

**Utilisation** :
```php
$router->middleware(new CorsMiddleware([
    'allowedOrigins' => ['*'], // Toutes les origines
]));
```

**Headers CORS** :
- `Access-Control-Allow-Origin`
- `Access-Control-Allow-Methods`
- `Access-Control-Allow-Headers`
- `Access-Control-Expose-Headers`
- `Access-Control-Max-Age`
- `Access-Control-Allow-Credentials`

**Comportement** :
- Répond automatiquement aux requêtes OPTIONS (preflight)
- Ajoute les headers CORS à la réponse

---

### 6. RequestValidationMiddleware

**Rôle** : Validation des requêtes HTTP

**Fichier** : `src/Core/Middleware/RequestValidationMiddleware.php`

**Fonctionnalités** :
- Validation des données POST
- Validation des paramètres de route
- Validation des headers
- Messages d'erreur personnalisables

**Configuration** :
```php
$middleware = new RequestValidationMiddleware([
    'rules' => [
        'email' => 'required|email',
        'password' => 'required|min:8',
    ],
    'messages' => [
        'email.required' => 'L\'email est requis',
    ],
]);
```

**Utilisation** :
```php
$router->middleware(new RequestValidationMiddleware([
    'rules' => [
        'name' => 'required|string|max:255',
        'age' => 'required|integer|min:18',
    ],
]));
```

**Réponse** :
- Retourne `422 Unprocessable Entity` si la validation échoue
- Retourne les erreurs de validation en JSON

---

## Ordre d'Exécution

L'ordre des middlewares est important. Voici un ordre recommandé :

```php
// 1. CORS (doit être en premier pour gérer les preflight)
$router->middleware(new CorsMiddleware([...]));

// 2. Security Headers
$router->middleware(new SecurityHeadersMiddleware([...]));

// 3. Rate Limiting
$router->middleware(new RateLimitMiddleware(100, 60));

// 4. CSRF (après CORS et Rate Limiting)
$router->middleware(new CsrfMiddleware());

// 5. Request Validation
$router->middleware(new RequestValidationMiddleware([...]));

// 6. Compression (en dernier, modifie la réponse)
$router->middleware(new CompressionMiddleware());
```

---

## Création d'un Middleware Personnalisé

**Exemple** :
```php
use JulienLinard\Core\Middleware\MiddlewareInterface;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class CustomMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): ?Response
    {
        // Vérifier quelque chose
        if ($request->getHeader('X-Custom-Header') === null) {
            return new Response(400, 'Header manquant');
        }
        
        // Continuer l'exécution
        return null;
    }
}
```

**Utilisation** :
```php
$router->middleware(new CustomMiddleware());
```

---

## Middlewares Globaux vs Routes Spécifiques

### Middlewares Globaux

Appliqués à toutes les routes :

```php
$router->middleware(new CsrfMiddleware());
```

### Middlewares par Route

Appliqués à des routes spécifiques (dépend de php-router) :

```php
$router->get('/api/users', [UserController::class, 'index'])
    ->middleware(new RateLimitMiddleware(10, 60));
```

---

## Bonnes Pratiques

1. **Ordre** : Placez les middlewares dans l'ordre logique (CORS → Security → Rate Limit → CSRF → Validation → Compression)

2. **Performance** : Les middlewares sont exécutés pour chaque requête. Évitez les opérations coûteuses.

3. **Réponses** : Retournez `null` pour continuer, une `Response` pour arrêter.

4. **Exceptions** : Les exceptions dans les middlewares ne sont pas capturées automatiquement. Utilisez un try-catch si nécessaire.

5. **Tests** : Testez vos middlewares avec différents scénarios (requêtes valides, invalides, limites, etc.)

---

## Dépannage

### Le middleware ne s'exécute pas

**Cause** : Le middleware n'est pas enregistré ou l'ordre est incorrect.

**Solution** : Vérifier que `$router->middleware()` est appelé avant `$router->handle()`.

### CSRF token invalide

**Cause** : Le token n'est pas inclus dans le formulaire ou la session a expiré.

**Solution** : Vérifier que `CsrfMiddleware::field()` est inclus dans le formulaire.

### Rate limit toujours actif

**Cause** : Le cache n'est pas nettoyé ou les fichiers de stockage persistent.

**Solution** : Vérifier les permissions du dossier de stockage et nettoyer périodiquement.

---

**Note** : Les middlewares sont compatibles avec `php-router`. Voir la documentation de `php-router` pour plus de détails sur l'intégration.

