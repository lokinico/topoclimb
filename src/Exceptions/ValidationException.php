<?php

namespace TopoclimbCH\Exceptions;

/**
 * Exception lancée lors d'erreurs de validation
 */
class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(array $errors, string $message = "Erreur de validation", int $code = 400, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Récupère les erreurs de validation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Récupère les erreurs formatées pour l'affichage
     */
    public function getFormattedErrors(): array
    {
        $formatted = [];
        foreach ($this->errors as $field => $messages) {
            if (is_array($messages)) {
                $formatted[$field] = implode(', ', $messages);
            } else {
                $formatted[$field] = $messages;
            }
        }
        return $formatted;
    }

    /**
     * Convertit les erreurs en chaîne de caractères
     */
    public function getErrorsAsString(): string
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            if (is_array($fieldErrors)) {
                foreach ($fieldErrors as $error) {
                    $messages[] = "$field: $error";
                }
            } else {
                $messages[] = "$field: $fieldErrors";
            }
        }
        return implode('; ', $messages);
    }
}