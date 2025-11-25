<?php

namespace JulienLinard\Core\View;

use JulienLinard\Core\Middleware\CsrfMiddleware;

/**
 * Helpers pour les vues
 */
class ViewHelper
{
    /**
     * Échappe une valeur pour éviter les injections XSS
     *
     * @param mixed $value Valeur à échapper
     * @return string Valeur échappée
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Alias court pour escape()
     */
    public static function e(mixed $value): string
    {
        return self::escape($value);
    }

    /**
     * Formate une date
     *
     * @param string|int|\DateTime $date Date à formater
     * @param string $format Format de date (par défaut: 'd/m/Y H:i')
     * @return string Date formatée
     */
    public static function date(mixed $date, string $format = 'd/m/Y H:i'): string
    {
        if (is_string($date) || is_int($date)) {
            $date = new \DateTime(is_int($date) ? '@' . $date : $date);
        }
        
        if ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        return '';
    }

    /**
     * Formate un nombre avec séparateurs
     *
     * @param float|int $number Nombre à formater
     * @param int $decimals Nombre de décimales
     * @return string Nombre formaté
     */
    public static function number(float|int $number, int $decimals = 2): string
    {
        return number_format($number, $decimals, ',', ' ');
    }

    /**
     * Formate un prix
     *
     * @param float|int $price Prix à formater
     * @param string $currency Devise (par défaut: '€')
     * @return string Prix formaté
     */
    public static function price(float|int $price, string $currency = '€'): string
    {
        return self::number($price, 2) . ' ' . $currency;
    }

    /**
     * Tronque une chaîne
     *
     * @param string $string Chaîne à tronquer
     * @param int $length Longueur maximale
     * @param string $suffix Suffixe à ajouter (par défaut: '...')
     * @return string Chaîne tronquée
     */
    public static function truncate(string $string, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }
        
        return mb_substr($string, 0, $length) . $suffix;
    }

    /**
     * Retourne le token CSRF actuel
     *
     * @return string Token CSRF
     */
    public static function csrfToken(): string
    {
        return CsrfMiddleware::getToken();
    }

    /**
     * Génère un champ hidden pour le token CSRF dans les formulaires
     *
     * @param string $tokenName Nom du champ (par défaut: '_token')
     * @return string HTML du champ hidden
     */
    public static function csrfField(string $tokenName = '_token'): string
    {
        return CsrfMiddleware::field($tokenName);
    }

    /**
     * Génère une URL depuis le nom d'une route
     *
     * @param string $name Nom de la route
     * @param array $params Paramètres de route (pour routes dynamiques)
     * @param array $queryParams Paramètres de query string
     * @return string|null URL générée ou null si la route n'existe pas
     */
    public static function route(string $name, array $params = [], array $queryParams = []): ?string
    {
        $app = \JulienLinard\Core\Application::getInstanceOrFail();
        $router = $app->getRouter();
        return $router->url($name, $params, $queryParams);
    }
}

