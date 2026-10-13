<?php

namespace App\Services\EmploiMa;

/**
 * Parse.bot answered 429 (docs/project.md F11).
 */
class RateLimited extends RequestFailed
{
    /**
     * Create a new instance.
     */
    public function __construct()
    {
        parent::__construct('emploi.ma is rate limiting this key.');
    }
}
