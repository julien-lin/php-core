<?php

declare(strict_types=1);

namespace JulienLinard\Core\Session;

/**
 * Gestion des sessions PHP avec méthodes utilitaires
 */
class Session
{
    // Constantes pour les clés de session couramment utilisées
    public const USER = 'user';
    public const FORM_RESULT = 'form_result';
    public const FORM_SUCCESS = 'form_success';
    public const FLASH_MESSAGE = 'flash_message';
    public const FLASH_ERROR = 'flash_error';

    /**
     * Démarre la session si elle n'est pas déjà démarrée
     * 
     * Note: Pour une configuration sécurisée complète, utilisez Application::start()
     * qui configure automatiquement les paramètres de sécurité de session.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Assure que la session est démarrée (méthode utilitaire optimisée)
     * 
     * Cette méthode est utilisée en interne pour éviter la duplication de code
     */
    private static function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Définit une valeur dans la session
     *
     * @param string $key Clé
     * @param mixed $value Valeur
     */
    public static function set(string $key, mixed $value): void
    {
        self::ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Récupère une valeur de la session
     *
     * @param string $key Clé
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @return mixed Valeur ou valeur par défaut
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Vérifie si une clé existe dans la session
     *
     * @param string $key Clé
     * @return bool True si la clé existe
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Supprime une valeur de la session
     *
     * @param string $key Clé
     */
    public static function remove(string $key): void
    {
        self::ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Ajoute un message flash (disparaît après la première lecture)
     *
     * @param string $key Clé du message flash
     * @param mixed $value Valeur du message
     */
    public static function flash(string $key, mixed $value): void
    {
        self::set('_flash.' . $key, $value);
    }

    /**
     * Vérifie si un message flash existe
     *
     * @param string $key Clé du message flash
     * @return bool True si le message flash existe
     */
    public static function hasFlash(string $key): bool
    {
        self::ensureStarted();
        return isset($_SESSION['_flash.' . $key]);
    }

    /**
     * Récupère un message flash et le supprime
     *
     * @param string $key Clé du message flash
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur du message flash
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        $flashKey = '_flash.' . $key;
        $value = $_SESSION[$flashKey] ?? $default;
        unset($_SESSION[$flashKey]);
        return $value;
    }

    /**
     * Régénère l'ID de session (sécurité)
     *
     * @param bool $deleteOldSession Si true, supprime l'ancienne session
     */
    public static function regenerate(bool $deleteOldSession = false): void
    {
        self::ensureStarted();
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Vide toutes les données de la session
     */
    public static function flush(): void
    {
        self::ensureStarted();
        $_SESSION = [];
    }

    /**
     * Détruit la session
     */
    public static function destroy(): void
    {
        self::ensureStarted();
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Retourne toutes les données de la session
     *
     * @return array Toutes les données de la session
     */
    public static function all(): array
    {
        self::ensureStarted();
        return $_SESSION;
    }
}

