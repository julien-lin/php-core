<?php

declare(strict_types=1);

namespace JulienLinard\Core\Form;

/**
 * Représente un message de succès de formulaire
 */
class FormSuccess
{
    /**
     * Constructeur
     *
     * @param string $message Message de succès
     * @param string $field Nom du champ concerné (optionnel)
     */
    public function __construct(
        private string $message,
        private string $field = ''
    ) {
    }

    /**
     * Retourne le message de succès
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Retourne le nom du champ concerné
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Retourne une représentation string
     */
    public function __toString(): string
    {
        return $this->message;
    }
}

