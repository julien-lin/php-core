<?php

declare(strict_types=1);

namespace JulienLinard\Core\Exceptions;

/**
 * Exception levée lors d'erreurs de validation (422)
 */
class ValidationException extends FrameworkException
{
    private array $errors;

    public function __construct(string $message = 'Validation failed', array $errors = [], int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

