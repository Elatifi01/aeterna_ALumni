<?php

namespace App\Jobs;

use App\Exceptions\PortalUnavailableException;
use App\Models\Alumni;
use App\Services\AlumniAccountService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Give one imported alumni a portal account and mail them the credentials (docs/project.md F12).
 */
class SendAlumniCredentials implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new instance.
     */
    public function __construct(public readonly int $alumniId) {}

    /**
     * Execute the job.
     */
    public function handle(AlumniAccountService $accounts): void
    {
        $alumni = Alumni::acrossOrganizations()->find($this->alumniId);

        if ($alumni === null || $alumni->has_account) {
            return;
        }

        try {
            $accounts->provision($alumni);
        } catch (PortalUnavailableException) {
            return;
        }
    }
}
