<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when an alumni's portal credentials were set but the e-mail carrying them could not be sent (docs/project.md F9).
 */
class CredentialsNotDeliveredException extends Exception
{
    /**
     * Create a new instance.
     */
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Le compte a ete cree mais l e-mail n a pas pu etre envoye. Utilisez « Renvoyer les identifiants » pour reessayer.');
    }
}
