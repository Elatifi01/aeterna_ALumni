<?php

namespace App\Mail;

use App\Models\Alumni;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "mot de passe oublie" link for the portal (docs/project.md F9).
 */
class AlumniPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly Alumni $alumni,
        public readonly Organization $organization,
        public readonly string $token,
    ) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reinitialiser votre mot de passe — {$this->organization->name}",
        );
    }

    /**
     * The message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.alumni-password-reset',
            with: [
                'alumni' => $this->alumni,
                'organizationName' => $this->organization->name,
                'resetUrl' => route('portal.password.reset', [
                    'organization' => $this->organization->slug,
                    'token' => $this->token,
                    'email' => $this->alumni->email,
                ]),
                'expiresMinutes' => (int) config('portal.reset_link_expires_minutes', 60),
            ],
        );
    }
}
