<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * The model could not be reached (docs/project.md F14).
 */
class AssistantUnavailable extends RuntimeException
{
    /**
     * Create a new instance.
     */
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Aeterna Assistant n est pas disponible pour le moment. Verifiez que le service d IA local est demarre.');
    }
}
