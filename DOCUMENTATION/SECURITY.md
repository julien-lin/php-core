# Mesures de Sécurité

**Version** : 1.4.4+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le framework `core-php` intègre de nombreuses mesures de sécurité par défaut pour protéger les applications contre les vulnérabilités courantes. Cette documentation décrit toutes les mesures de sécurité implémentées.

---

## 1. Protection CSRF

### Implémentation

Le `CsrfMiddleware` protège contre les attaques Cross-Site Request Forgery.

**Fonctionnalités** :
- Génération de tokens CSRF sécurisés (64 caractères hexadécimaux)
- Vérification pour les méthodes POST, PUT, PATCH, DELETE
- Support des tokens dans POST et headers (`X-CSRF-TOKEN`)
- Exclusion de chemins (ex: `/api`)
- Régénération automatique du token après chaque requête

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

**Sécurité** :
- Utilise `hash_equals()` pour la comparaison (protection timing attack)
- Tokens générés avec `random_bytes(32)` (cryptographiquement sécurisé)

---

## 2. Headers de Sécurité HTTP

### Implémentation

Le `SecurityHeadersMiddleware` ajoute des headers de sécurité HTTP.

**Headers supportés** :

#### Content-Security-Policy (CSP)
Protection contre les attaques XSS.

**Par défaut** : `default-src 'self'`

**Configuration** :
```php
$middleware = new SecurityHeadersMiddleware([
    'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'",
]);
```

#### Strict-Transport-Security (HSTS)
Force l'utilisation de HTTPS.

**Par défaut** : `null` (désactivé)

**Configuration** :
```php
$middleware = new SecurityHeadersMiddleware([
    'hsts' => 'max-age=31536000; includeSubDomains',
]);
```

**Note** : HSTS n'est ajouté que si la requête est en HTTPS.

#### X-Frame-Options
Protection contre le clickjacking.

**Par défaut** : `SAMEORIGIN`

**Valeurs** : `DENY`, `SAMEORIGIN`, `ALLOW-FROM`

#### X-Content-Type-Options
Empêche le MIME sniffing.

**Par défaut** : `nosniff`

#### Referrer-Policy
Contrôle les informations de referrer.

**Par défaut** : `strict-origin-when-cross-origin`

#### Permissions-Policy
Contrôle les fonctionnalités du navigateur.

**Par défaut** : `null` (désactivé)

#### X-XSS-Protection
Protection XSS (déprécié mais utile pour anciens navigateurs).

**Par défaut** : `1; mode=block`

---

## 3. Sessions Sécurisées

### Configuration Automatique

L'`Application` configure automatiquement les sessions de manière sécurisée.

**Mesures** :
- `session.use_strict_mode = 1` : Empêche l'utilisation d'IDs de session non initialisés
- `session.cookie_httponly = 1` : Empêche l'accès JavaScript au cookie
- `session.cookie_secure = 1` : Cookie uniquement en HTTPS (si détecté)
- `session.cookie_samesite = Strict` : Protection CSRF supplémentaire
- `session.use_only_cookies = 1` : Pas d'URL rewriting

**Régénération Périodique** :
- L'ID de session est régénéré toutes les 15 minutes
- Régénération à la première utilisation
- Utilise `session_regenerate_id(true)` (supprime l'ancien ID)

**Code** :
```php
private const SESSION_REGENERATION_INTERVAL = 900; // 15 minutes
```

---

## 4. Protection Open Redirect

### Implémentation

Le `Controller` valide les URLs de redirection pour éviter les open redirects.

**Méthode** : `isValidLocalUrl(string $url): bool`

**Règles** :
- Autorise seulement les URLs relatives (`/path`)
- Autorise les URLs absolues du même domaine
- Rejette les URLs vers d'autres domaines

**Utilisation** :
```php
protected function redirect(string $uri, int $status = 302): Response
{
    $uri = $this->validateRedirectUri($uri);
    // ...
}
```

**Exemple** :
```php
// ✅ Autorisé
$this->redirect('/dashboard');
$this->redirect('https://example.com/path');

// ❌ Rejeté
$this->redirect('https://evil.com');
```

---

## 5. Protection Mass Assignment

### Implémentation

Le `Model` protège contre les attaques Mass Assignment.

**Mécanismes** :
- `$fillable` : Whitelist des champs autorisés
- `$guarded` : Blacklist des champs protégés
- Protection par défaut du champ `id`

**Exemple** :
```php
class User extends Model
{
    protected array $fillable = ['name', 'email'];
    protected array $guarded = ['role', 'is_admin'];
}
```

**Comportement** :
- Seuls les champs dans `$fillable` peuvent être assignés
- Les champs dans `$guarded` sont toujours protégés
- Le champ `id` est toujours protégé par défaut

---

## 6. Redaction des Données Sensibles

### Implémentation

L'`ErrorHandler` et le `SimpleLogger` redactent automatiquement les données sensibles dans les logs.

**Clés sensibles détectées** :
- `password`, `passwd`, `pwd`
- `token`, `access_token`, `refresh_token`
- `api_key`, `api_secret`, `secret`
- `private_key`
- `credit_card`, `card_number`, `cvv`, `cvc`
- `ssn`, `social_security`
- `authorization`, `cookie`, `session_id`, `jwt`

**Comportement** :
- Les valeurs sensibles sont remplacées par `[REDACTED]`
- Fonctionne récursivement dans les tableaux
- Appliqué automatiquement avant le logging

**Exemple** :
```php
// Avant
['email' => 'user@example.com', 'password' => 'secret123']

// Après
['email' => 'user@example.com', 'password' => '[REDACTED]']
```

---

## 7. Hash Sécurisés

### Remplacement de MD5

Tous les hash de cache utilisent des algorithmes sécurisés.

**Avant** : MD5 (non sécurisé)

**Après** :
- `xxh3` si disponible (PHP 8.1+)
- `sha256` sinon

**Fichiers modifiés** :
- `View::getCacheFilePath()` : Hash des clés de cache
- `ErrorHandler::generateErrorPageHtml()` : Hash des clés de cache

**Code** :
```php
if (function_exists('hash') && in_array('xxh3', hash_algos(), true)) {
    $hash = hash('xxh3', $data);
} else {
    $hash = hash('sha256', $data);
}
```

---

## 8. Rate Limiting

### Implémentation

Le `RateLimitMiddleware` limite le nombre de requêtes par IP/route.

**Fonctionnalités** :
- Limitation par IP et route
- Fenêtre de temps configurable
- Protection contre les race conditions (flock)
- Cache mémoire pour performance

**Sécurité** :
- Utilise SHA256 pour les clés de hash (pas MD5)
- Permissions sécurisées (0750) pour les fichiers
- Verrouillage avec `flock()` pour éviter les race conditions

**Utilisation** :
```php
$router->middleware(new RateLimitMiddleware(100, 60)); // 100 req/min
```

**Réponse** :
- Retourne `429 Too Many Requests` si la limite est dépassée

---

## 9. Validation de la Taille POST

### Implémentation

L'`Application` valide la taille des requêtes POST.

**Configuration** :
```php
$app->setMaxPostSize(52_428_800); // 50MB par défaut
```

**Comportement** :
- Utilise `Content-Length` pour éviter de charger le corps en mémoire
- Retourne `413 Payload Too Large` si dépassé
- Ignore les requêtes non-POST ou sans `Content-Length`

**Code** :
```php
private function validatePostSize(): void
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $this->maxPostSize) {
        http_response_code(413);
        die('Payload too large');
    }
}
```

---

## 10. Sanitization des Headers

### Implémentation

La classe `Response` (php-router) sanitize automatiquement les headers.

**Protection** :
- Suppression des caractères non autorisés dans les noms
- Suppression des retours à la ligne (CRLF injection)
- Suppression des caractères de contrôle

**Méthodes** :
- `sanitizeHeaderName()` : Nettoie le nom du header
- `sanitizeHeaderValue()` : Nettoie la valeur du header

**Exemple** :
```php
// Avant
$response->setHeader("Location", "http://example.com\r\nSet-Cookie: evil=1");

// Après (sanitizé)
Location: http://example.com
```

---

## 11. Validation des Fichiers de Configuration

### Implémentation

Le `ConfigLoader` valide les noms de fichiers de configuration.

**Règles** :
- Seuls les caractères alphanumériques, tirets et underscores sont autorisés
- Rejette les fichiers avec caractères spéciaux

**Exemple** :
```php
// ✅ Autorisé
config/app.php
config/database.php

// ❌ Rejeté
config/app!.php
config/../evil.php
```

---

## 12. Permissions de Fichiers

### Implémentation

Les fichiers créés par le framework utilisent des permissions sécurisées.

**Permissions** :
- Fichiers de log : `0644` (rw-r--r--)
- Dossiers de cache : `0750` (rwxr-x---)
- Fichiers de rate limiting : `0750` (rwxr-x---)

**Avant** : `0777` (trop permissif)

**Après** : Permissions restrictives par défaut

---

## 13. Protection contre les Dépendances Circulaires

### Implémentation

Le `Container` détecte et empêche les dépendances circulaires.

**Protection** :
- Détection des dépendances circulaires
- Limite de profondeur (20 niveaux)
- Messages d'erreur explicites

**Exemple** :
```php
// Dépendance circulaire détectée
RuntimeException: Circular dependency detected for class A.
Dependency chain: A → B → A
```

---

## Bonnes Pratiques de Sécurité

### 1. Toujours utiliser HTTPS en production

```php
// Vérifier HTTPS
if (!$app->isHttps()) {
    // Rediriger vers HTTPS
}
```

### 2. Configurer CSP strictement

```php
$middleware = new SecurityHeadersMiddleware([
    'csp' => "default-src 'self'; script-src 'self'",
]);
```

### 3. Limiter les origines CORS

```php
$middleware = new CorsMiddleware('https://example.com', true);
```

### 4. Utiliser des tokens CSRF partout

```php
// Dans tous les formulaires
<?= CsrfMiddleware::field() ?>
```

### 5. Valider toutes les entrées utilisateur

```php
// Utiliser RequestValidationMiddleware ou Validator
$validator = new Validator($data, $rules);
```

### 6. Ne jamais logger de données sensibles

```php
// ✅ Bon (redaction automatique)
$logger->error('Login failed', ['email' => $email, 'password' => $password]);

// ❌ Mauvais (ne pas logger directement)
error_log('Password: ' . $password);
```

### 7. Régénérer les sessions après login

```php
// Déjà fait automatiquement toutes les 15 minutes
// Mais peut être forcé après un changement de privilèges
session_regenerate_id(true);
```

### 8. Utiliser des hash sécurisés

```php
// ✅ Bon
$hash = hash('sha256', $data);

// ❌ Mauvais
$hash = md5($data);
```

---

## Checklist de Sécurité

Avant de déployer en production, vérifier :

- [ ] HTTPS est activé et configuré
- [ ] Les middlewares de sécurité sont activés
- [ ] Les sessions sont configurées correctement
- [ ] Les tokens CSRF sont inclus dans tous les formulaires
- [ ] Les headers de sécurité sont configurés
- [ ] Le rate limiting est activé
- [ ] Les permissions de fichiers sont correctes
- [ ] Les données sensibles ne sont pas loggées
- [ ] Les redirections sont validées
- [ ] Les modèles protègent contre Mass Assignment
- [ ] La validation des entrées est en place
- [ ] Les dépendances sont à jour

---

## Audit de Sécurité

### Outils Recommandés

1. **OWASP ZAP** : Scanner de vulnérabilités web
2. **Burp Suite** : Proxy et scanner de sécurité
3. **PHP Security Checker** : Vérification des dépendances
4. **SonarQube** : Analyse de code statique

### Tests de Sécurité

1. **CSRF** : Tester avec des requêtes cross-origin
2. **XSS** : Injecter du JavaScript dans les entrées
3. **SQL Injection** : Tester les requêtes SQL (si applicable)
4. **Open Redirect** : Tester les redirections
5. **Mass Assignment** : Tester l'assignation de champs protégés
6. **Rate Limiting** : Tester les limites de requêtes
7. **Session Fixation** : Tester la régénération de session

---

## Références

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

---

**Note** : La sécurité est un processus continu. Cette documentation décrit les mesures actuelles, mais de nouvelles vulnérabilités peuvent apparaître. Restez à jour avec les meilleures pratiques de sécurité.

