<?php

namespace App\Services;

use App\Exceptions\CredentialsNotDeliveredException;
use App\Exceptions\PortalUnavailableException;
use App\Mail\AlumniCredentialsMail;
use App\Models\Alumni;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Portal accounts for alumni (docs/project.md F9).
 */
class AlumniAccountService
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly PlanLimitService $planLimits) {}

    /**
     * Give an alumni an account and mail them the credentials.
     *
     * @throws PortalUnavailableException when the plan has no portal
     * @throws CredentialsNotDeliveredException when the mail could not be sent
     */
    public function provision(Alumni $alumni): bool
    {
        $this->assertPortalAvailable($alumni);

        if ($alumni->has_account) {
            return false;
        }

        $this->issueCredentials($alumni);

        return true;
    }

    /**
     * Replace the password and mail it again.
     *
     * @throws PortalUnavailableException when the plan has no portal
     * @throws CredentialsNotDeliveredException when the mail could not be sent
     */
    public function resend(Alumni $alumni): void
    {
        $this->assertPortalAvailable($alumni);

        $this->issueCredentials($alumni);
    }

    /**
     * Take the account away without touching the alumni record.
     */
    public function revoke(Alumni $alumni): void
    {
        $alumni->forceFill([
            'password' => null,
            'remember_token' => null,
            'must_change_password' => true,
            'credentials_sent_at' => null,
            'password_changed_at' => null,
            'login_token' => null,
            'login_token_expires_at' => null,
        ])->save();
    }

    /**
     * Whether the organization's plan includes the portal at all.
     */
    public function isAvailableTo(Organization $organization): bool
    {
        return $this->planLimits->allows($organization, 'alumni_portal');
    }

    /**
     * Generate a password, store it hashed and mail it to the alumni.
     */
    private function issueCredentials(Alumni $alumni): void
    {
        $password = $this->generatePassword();

        $alumni->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'credentials_sent_at' => now(),
            'password_changed_at' => null,
            'remember_token' => null,
            'login_token' => null,
            'login_token_expires_at' => null,
        ])->save();

        $organization = $alumni->organization()->withoutGlobalScopes()->first();

        try {
            Mail::to($alumni->email)->send(
                new AlumniCredentialsMail($alumni, $organization, $password),
            );
        } catch (Throwable $exception) {
            Log::error('Portal credentials could not be sent.', [
                'alumni_id' => $alumni->id,
                'error' => $exception->getMessage(),
            ]);

            throw new CredentialsNotDeliveredException;
        }
    }

    /**
     * Alphanumeric only, and deliberately so: this password gets retyped by hand from a mail, often on a phone, and punctuation invites transcription errors.
     */
    private function generatePassword(): string
    {
        return Str::password(
            length: (int) config('portal.generated_password_length', 12),
            letters: true,
            numbers: true,
            symbols: false,
            spaces: false,
        );
    }

    /**
     * Refuse unless the organization's plan includes the alumni portal.
     *
     * @throws PortalUnavailableException
     */
    private function assertPortalAvailable(Alumni $alumni): void
    {
        $organization = $alumni->organization()->withoutGlobalScopes()->first();

        if ($organization === null || ! $this->isAvailableTo($organization)) {
            throw new PortalUnavailableException;
        }
    }
}
