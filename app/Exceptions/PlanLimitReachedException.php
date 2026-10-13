<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when a create would exceed the organization's plan limit (docs/project.md F6).
 */
class PlanLimitReachedException extends Exception
{
    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly string $resource,
        public readonly int $limit,
        string $message,
    ) {
        parent::__construct($message);
    }
}
