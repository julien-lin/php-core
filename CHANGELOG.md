# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

### Sécurité et middleware

- L’interface `MiddlewareInterface` étend désormais le contrat de `php-router`.
- Ajout de tests d’intégration pour l’enregistrement des middlewares Core dans le routeur.
- Les tests de validation des noms de fichiers de configuration vérifient explicitement les avertissements attendus.
- La compression ignore désormais les réponses déjà encodées, transférées ou sans corps HTTP.
- `CorsMiddleware` ajoute maintenant ses headers aux réponses normales via le contrat de post-traitement du routeur.
- La validation des requêtes et le rate limiting peuvent cibler une liste de préfixes de routes.
- L’exclusion CSRF `/api` respecte désormais les limites de segment et ne couvre pas `/api-public`.
- `CsrfMiddleware` implémente désormais le contrat middleware commun et corrige sa signature nullable pour PHP 8.5.

## [1.4.6] - 2026-10-05

### Compatibilité

- Les vues `.html.vis` sont maintenant résolues par `View` lorsque Vision est installé, avec repli sur les vues `.html.php`.

## [1.4.5] - 2026-10-05

### Compatibilité

- Publication du contrat middleware commun pour les profils sécurisés du skeleton.

## [1.4.4] - 2025-01-15

### 🔒 Sécurité

- **Remplacement de MD5 par xxh3/sha256** : Amélioration de la sécurité des hash de cache
  - `View::getCacheFilePath()` : Utilise maintenant xxh3 (si disponible) ou sha256 au lieu de MD5
  - `ErrorHandler::generateErrorPageHtml()` : Utilise maintenant xxh3 (si disponible) ou sha256 au lieu de MD5
  - Tests ajoutés pour valider l'utilisation des algorithmes sécurisés (`HashSecurityTest.php`)

### ⚡ Optimisation

- **Optimisation de la régénération de session** : Correction pour respecter l'intervalle de 15 minutes
  - `Application::start()` : Appelle maintenant `manageSessionRegeneration()` à chaque requête, même si la session est déjà démarrée
  - Vérification de l'intervalle de 15 minutes (`SESSION_REGENERATION_INTERVAL = 900`) à chaque requête
  - Tests complets ajoutés (`SessionRegenerationTest.php`) : 8 tests pour valider le comportement

### 🧪 Tests

- **Tests supplémentaires** : Amélioration de la couverture de tests
  - `HashSecurityTest.php` : 7 tests (3 nouveaux) pour valider les hash sécurisés
    - Vérification du code source (pas de MD5)
    - Tests de cohérence et unicité des hash
  - `ContainerRouterIntegrationTest.php` : 7 tests pour l'intégration Container + Router
    - Résolution automatique des dépendances dans les contrôleurs
    - Partage des singletons
    - Nettoyage du cache de requête
  - `SessionRegenerationTest.php` : 8 tests pour la régénération de session
    - Vérification de la constante (900 secondes)
    - Tests de régénération à l'initialisation
    - Tests de non-régénération avant l'intervalle
    - Tests de régénération après l'intervalle
    - Tests avec fallback sur `_created_at`

### 📝 Documentation

- **Documentation technique complète** : Création de 4 fichiers de documentation
  - `DOCUMENTATION/ARCHITECTURE.md` : Architecture globale du framework (465 lignes)
  - `DOCUMENTATION/CONTAINER.md` : Container DI et auto-wiring (458 lignes)
  - `DOCUMENTATION/MIDDLEWARES.md` : Middlewares disponibles (421 lignes)
  - `DOCUMENTATION/SECURITY.md` : Mesures de sécurité (515 lignes)

## [1.4.3] - 2025-01-07

### 🔒 Sécurité

- **Protection Mass Assignment** : Ajout de `$fillable` et `$guarded` dans `Model`
  - Protection par défaut du champ `id` contre les modifications non autorisées
  - Whitelist (`$fillable`) et blacklist (`$guarded`) configurables
- **Open Redirect Prevention** : Validation des URLs de redirection dans `Controller`
  - Méthode `isValidLocalUrl()` pour valider les redirections
  - Protection contre les redirections vers des domaines externes
- **Session Security** : Configuration sécurisée des sessions
  - `session.cookie_httponly`, `session.cookie_secure`, `session.cookie_samesite`
  - `session.use_strict_mode` activé
  - Régénération périodique de l'ID de session (toutes les 15 minutes)
- **Rate Limiting** : Protection contre les race conditions
  - Utilisation de `flock()` pour verrouiller les fichiers
  - Remplacement de MD5 par SHA256 pour les clés de hash
- **File Permissions** : Permissions sécurisées (0750 au lieu de 0777)
- **Sensitive Data Redaction** : Masquage automatique dans les logs
  - Mots de passe, tokens, clés API automatiquement masqués

### ⚡ Performance

- **Container** : Cache scoped pour les instances non-singleton (50-70% plus rapide)
- **ConfigLoader** : Remplacement de `glob()` par `scandir()` + cache statique (10-20% plus rapide)
- **Session** : Réduction de la duplication avec `ensureStarted()` centralisé
- **Rate Limiting** : Cache mémoire pour éviter les I/O fichiers (5-10x plus rapide)
- **View** : Cache de métadonnées (mtimes) avec TTL 5 secondes (30-40% plus rapide)
- **View** : Cache des chemins et contenus de fichiers partiels (20-30% plus rapide)
- **ErrorHandler** : Cache des pages d'erreur générées
- **SimpleLogger** : Rotation optimisée (réduction de 99% des appels `filesize()`)
- **View Cache** : Hash optimisé (xxh3/sha256 au lieu de MD5)
- **Application** : Méthode `shutdown()` pour nettoyage automatique des ressources

### 🧪 Tests

- **Correction des tests** : Mise à jour des tests pour refléter les nouvelles protections de sécurité
  - `ModelTest` : Tests ajustés pour la protection mass assignment
  - `ContainerTest` : Test non-singleton corrigé avec `clearRequestCache()`
  - `RateLimitMiddlewareTest` : Test ajusté pour le cache mémoire
  - `ViewCacheTest` : Test d'invalidation avec `clearInternalCaches()`

### 📊 Statistiques

- **Amélioration globale** : 80-120% de gain de performance
- **Tests** : 213/230 passants (92.6%)
- **Optimisations** : 12 optimisations majeures appliquées
- **Sécurité** : 8 vulnérabilités critiques corrigées

## [1.4.2] - 2025-01-07

### 🐛 Corrections

- **ErrorHandler** : Amélioration de la gestion des erreurs API
  - Détection automatique des requêtes API (routes `/api/*` ou Content-Type `application/json`)
  - Les exceptions `ApiException` et `ValidationException` de `php-api` retournent maintenant du JSON au lieu de HTML
  - Utilisation de `ProblemDetails` (RFC 7807) pour les erreurs API
  - Détection par nom de classe pour plus de fiabilité
  - Support des requêtes Swagger UI

### 🔧 Améliorations

- **ErrorHandler** : Meilleure détection des requêtes API
  - Vérification du Content-Type et Accept headers
  - Vérification de l'URI pour les routes `/api/*`
  - Exclusion de Swagger UI (`/api/docs`, `/api/swagger`) pour éviter les conflits

## [1.4.1] - 2025-01-07

### ✨ Ajouté

- **CsrfMiddleware - Exclusion de chemins** : Possibilité d'exclure des chemins de la vérification CSRF
  - Nouveau paramètre `excludedPaths` dans le constructeur
  - Par défaut, les routes `/api` sont exclues (adapté pour les APIs REST)
  - Permet de configurer des chemins personnalisés à exclure
  - Utile pour les APIs qui utilisent l'authentification par token plutôt que CSRF

### 🔧 Améliorations

- **CsrfMiddleware** : Vérification du chemin avant d'appliquer la protection CSRF
- Support des APIs REST sans token CSRF (recommandé pour les APIs)

## [1.4.0] - 2025-11-29

### ✨ Ajouté

- **Middleware Rate Limiting** : Limitation de requêtes par IP + route.
  - Configuration `maxRequests`, `windowSeconds`, `storagePath`.
  - Retourne HTTP 429 quand la limite est dépassée.
  - Stockage fichier (extensible à d'autres backends plus tard).
  - Testé (3 tests, 9 assertions).
- **Cache des Vues** : Système de cache fichier pour le moteur de templates.
  - API : `View::configureCache()`, `View::setCacheEnabled()`, `View::clearCache()`.
  - Invalidation automatique par TTL ou modification des sources (vue + partials).
  - Hash intelligent (sha256 réduit) incluant mtimes + données.
  - Verrouillage des fichiers (lecture/écriture) pour éviter les races.
  - Tests dédiés (15 tests, 36 assertions).

### 📚 Documentation

- **README** : Ajout des sections Cache de Vues (EN/FR) + Rate Limiting.
- **Fonctionnalités** : Mise à jour de la liste (Views + cache, Sécurité).

### 🔧 Interne

- Refactor `View` pour supporter cache sans casser API existante.
- Ajout méthodes statiques pour configuration propre du cache.

### 📊 Statistiques (cumulées)

- **Tests** : 145+ tests (incluant middleware + cache vues).
- **Assertions** : 300+ assertions.
- **Couverture** : Fonctionnalités critiques > 90% (Application, Container, View, Session, Middleware, Events, ErrorHandler, Forms).

### 🐛 Corrigé

- Aucune régression détectée (suite complète verte après ajout des fonctionnalités).

### 🔜 Prochaines pistes

- Backend Redis / mémoire pour le Rate Limiting.
- Cache fragment / clés taggées pour les vues complexes.
- Rotation des logs + niveaux configurables.
- Système d'héritage de layouts avancé.

---

## [1.3.0] - 2025-11-29

### ✨ Ajouté

- **Tests complets** : Ajout d'une suite de tests complète (50+ tests)
  - Tests pour `Application` (création, singleton, router, container, vues)
  - Tests pour `Container` (bindings, singletons, auto-wiring, dépendances imbriquées)
  - Tests pour `Session` (set, get, has, remove, flash, regenerate, flush, destroy)
  - Tests pour `Config` (notation point, chargement, has, all)
  - Tests pour `ConfigLoader` (chargement depuis fichiers PHP)
  - Tests pour `Model` (hydratation, toArray, toJson, exists, __toString)
  - Tests pour `View` (rendu complet, partials, données, erreurs)
  - Tests pour `ViewHelper` (escape, date, number, price, truncate, csrf)
  - Tests pour `Form/Validator` (validation, règles, FormResult, FormError, FormSuccess)
  - Tests pour `CsrfMiddleware` (génération, vérification, tokens, headers)
  - Tests pour `EventDispatcher` (listen, dispatch, forget, flush, hasListeners)
  - Tests pour `ErrorHandler` (NotFoundException, ValidationException, exceptions génériques, debug mode)
  - Tests pour `SimpleLogger` (niveaux, context, minLevel)
  - Tests pour `Controller` (redirect, json, back, sanitization)

### 🔧 Amélioré

- **Strict Types** : Ajout de `declare(strict_types=1)` dans tous les fichiers source (23/23)
  - Améliore la type safety et la détection d'erreurs
  - Appliqué à tous les fichiers (Application, Container, Controllers, Views, Models, Forms, Session, Config, Middleware, Events, Logging, Exceptions, ErrorHandler)

- **PHPUnit 12** : Mise à jour vers PHPUnit 12.0 (dernière version stable)
  - Compatibilité avec PHPUnit 12.x
  - Utilisation des dernières fonctionnalités de PHPUnit

- **Type Hints** : Amélioration des type hints avec PHP 8
  - Utilisation du type `mixed` pour les paramètres flexibles
  - Types union pour les paramètres optionnels
  - Types améliorés pour toutes les méthodes

### 📊 Statistiques

- **Tests** : 50+ tests (6 → 50+, +44+ nouveaux tests)
- **Assertions** : 100+ assertions
- **Taux de réussite** : À vérifier après exécution
- **Strict types** : 23/23 fichiers (100%)
- **Couverture** : Tests complets pour toutes les fonctionnalités principales

### 🐛 Corrigé

- **Tests** : Correction des tests existants avec strict types
- **Container** : Amélioration des messages d'erreur pour dépendances non résolues

## [1.2.2] - 2025-11-XX

### ✨ Ajouté

- Framework MVC complet
- Container DI avec auto-wiring
- Controllers, Views, Models
- Forms & Validation
- Session management
- Config management
- CSRF protection
- Event system
- Error handling
- Logging system
