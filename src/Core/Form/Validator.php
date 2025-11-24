<?php

namespace JulienLinard\Core\Form;

use JulienLinard\Validator\Validator as PhpValidator;
use JulienLinard\Validator\ValidationResult as PhpValidationResult;

/**
 * Validateur de formulaires utilisant php-validator
 * 
 * Cette classe est un wrapper autour de php-validator qui maintient
 * la compatibilité avec l'API existante de core-php tout en bénéficiant
 * des fonctionnalités avancées de php-validator.
 */
class Validator
{
    /**
     * Instance du validateur php-validator
     */
    private PhpValidator $validator;

    public function __construct()
    {
        $this->validator = new PhpValidator();
    }

    /**
     * Vérifie qu'une valeur n'est pas vide
     */
    public function required(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        
        return $value !== null && $value !== '';
    }

    /**
     * Vérifie qu'une valeur est un email valide
     */
    public function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Vérifie qu'une valeur a une longueur minimale
     */
    public function min(string $value, int $min): bool
    {
        return mb_strlen($value) >= $min;
    }

    /**
     * Vérifie qu'une valeur a une longueur maximale
     */
    public function max(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    /**
     * Vérifie qu'une valeur a une longueur entre min et max
     */
    public function length(string $value, int $min, int $max): bool
    {
        $length = mb_strlen($value);
        return $length >= $min && $length <= $max;
    }

    /**
     * Vérifie qu'une valeur est un nombre
     */
    public function numeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Vérifie qu'une valeur est un entier
     */
    public function integer(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Vérifie qu'une valeur est un nombre décimal
     */
    public function float(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * Vérifie qu'une valeur correspond à un pattern regex
     */
    public function pattern(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Vérifie qu'une valeur est dans une liste de valeurs autorisées
     */
    public function in(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /**
     * Vérifie qu'une valeur est une URL valide
     */
    public function url(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Valide un tableau de données avec des règles
     * 
     * Utilise php-validator en interne pour bénéficier de toutes ses fonctionnalités
     * (règles personnalisées, messages multilingues, sanitization, etc.)
     *
     * @param array $data Données à valider
     * @param array $rules Règles de validation ['field' => 'required|email|min:5']
     * @return FormResult Résultat de la validation (compatible avec l'API existante)
     */
    public function validate(array $data, array $rules): FormResult
    {
        // Utiliser php-validator pour la validation
        $phpResult = $this->validator->validate($data, $rules);
        
        // Convertir le résultat en FormResult pour la compatibilité
        return $this->convertToFormResult($phpResult);
    }

    /**
     * Convertit un ValidationResult de php-validator en FormResult de core-php
     */
    private function convertToFormResult(PhpValidationResult $phpResult): FormResult
    {
        $formResult = new FormResult();

        // Convertir les erreurs
        foreach ($phpResult->getErrors() as $field => $errors) {
            foreach ($errors as $errorMessage) {
                $formResult->addError(new FormError($errorMessage, $field));
            }
        }

        return $formResult;
    }

    /**
     * Définit les messages personnalisés
     * 
     * @param array<string, string|array<string>> $messages Messages personnalisés
     * @return self
     */
    public function setCustomMessages(array $messages): self
    {
        $this->validator->setCustomMessages($messages);
        return $this;
    }

    /**
     * Définit la locale pour les messages d'erreur
     * 
     * @param string $locale Locale (ex: 'fr', 'en')
     * @return self
     */
    public function setLocale(string $locale): self
    {
        $this->validator->setLocale($locale);
        return $this;
    }

    /**
     * Active ou désactive la sanitization automatique
     * 
     * @param bool $sanitize Activer la sanitization
     * @return self
     */
    public function setSanitize(bool $sanitize): self
    {
        $this->validator->setSanitize($sanitize);
        return $this;
    }

    /**
     * Enregistre une règle personnalisée
     * 
     * @param \JulienLinard\Validator\Rules\RuleInterface $rule Règle personnalisée
     * @return self
     */
    public function registerRule(\JulienLinard\Validator\Rules\RuleInterface $rule): self
    {
        $this->validator->registerRule($rule);
        return $this;
    }

    /**
     * Récupère l'instance du validateur php-validator
     * 
     * Permet d'accéder directement aux fonctionnalités avancées de php-validator
     * 
     * @return PhpValidator
     */
    public function getPhpValidator(): PhpValidator
    {
        return $this->validator;
    }
}
