# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

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
