<?php
// src/Exceptions/AuthorizationException.php

namespace TopoclimbCH\Exceptions;

use Exception;

/**
 * Exception levée lorsqu'un utilisateur n'a pas les permissions nécessaires
 */
class AuthorizationException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = "Action non autorisée", int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
