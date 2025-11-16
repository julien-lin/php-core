<?php

namespace JulienLinard\Core\Form;

/**
 * Validateur de formulaires
 */
class Validator
{
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
     * @param array $data Données à valider
     * @param array $rules Règles de validation ['field' => 'required|email|min:5']
     * @return FormResult Résultat de la validation
     */
    public function validate(array $data, array $rules): FormResult
    {
        $result = new FormResult();

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $rulesList = explode('|', $ruleString);

            foreach ($rulesList as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParam = $ruleParts[1] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if (!$this->required($value)) {
                            $result->addError(new FormError("Le champ {$field} est requis.", $field));
                        }
                        break;

                    case 'email':
                        if ($value !== null && !$this->email($value)) {
                            $result->addError(new FormError("Le champ {$field} doit être un email valide.", $field));
                        }
                        break;

                    case 'min':
                        if ($value !== null && $ruleParam !== null && !$this->min($value, (int)$ruleParam)) {
                            $result->addError(new FormError("Le champ {$field} doit contenir au moins {$ruleParam} caractères.", $field));
                        }
                        break;

                    case 'max':
                        if ($value !== null && $ruleParam !== null && !$this->max($value, (int)$ruleParam)) {
                            $result->addError(new FormError("Le champ {$field} ne doit pas dépasser {$ruleParam} caractères.", $field));
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && !$this->numeric($value)) {
                            $result->addError(new FormError("Le champ {$field} doit être un nombre.", $field));
                        }
                        break;
                }
            }
        }

        return $result;
    }
}

