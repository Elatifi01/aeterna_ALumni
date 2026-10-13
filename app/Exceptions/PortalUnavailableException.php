<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when something portal-related is attempted for an organization whose plan does not include the portal (docs/project.md F6/F9).
 */
class PortalUnavailableException extends Exception
{
    /**
     * Create a new instance.
     */
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Votre plan ne comprend pas le portail alumni. Passez a un plan superieur pour en beneficier.');
    }
}
