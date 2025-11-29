<?php

declare(strict_types=1);

namespace JulienLinard\Core\Form;

/**
 * Résultat de validation d'un formulaire
 */
class FormResult
{
    /**
     * Messages d'erreur
     */
    private array $errors = [];

    /**
     * Message de succès
     */
    private ?FormSuccess $success = null;

    /**
     * Ajoute une erreur
     */
    public function addError(FormError $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    /**
     * Ajoute plusieurs erreurs
     */
    public function addErrors(array $errors): self
    {
        foreach ($errors as $error) {
            if ($error instanceof FormError) {
                $this->errors[] = $error;
            }
        }
        return $this;
    }

    /**
     * Définit le message de succès
     */
    public function addSuccess(FormSuccess $success): self
    {
        $this->success = $success;
        return $this;
    }

    /**
     * Vérifie s'il y a des erreurs
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Vérifie s'il y a un message de succès
     */
    public function hasSuccess(): bool
    {
        return $this->success !== null;
    }

    /**
     * Retourne toutes les erreurs
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retourne le message de succès
     */
    public function getSuccess(): ?FormSuccess
    {
        return $this->success;
    }

    /**
     * Retourne le premier message d'erreur
     */
    public function getFirstError(): ?FormError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Retourne les erreurs pour un champ spécifique
     */
    public function getErrorsForField(string $field): array
    {
        return array_filter(
            $this->errors,
            fn(FormError $error) => $error->getField() === $field
        );
    }

    /**
     * Vide toutes les erreurs
     */
    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Vide le message de succès
     */
    public function clearSuccess(): self
    {
        $this->success = null;
        return $this;
    }

    /**
     * Vide tout (erreurs et succès)
     */
    public function clear(): self
    {
        $this->clearErrors();
        $this->clearSuccess();
        return $this;
    }
}

