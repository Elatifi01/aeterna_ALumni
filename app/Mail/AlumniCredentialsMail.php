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
 * The welcome message that carries an alumni's portal credentials (docs/project.md F9).
 */
class AlumniCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly Alumni $alumni,
        public readonly ?Organization $organization,
        public readonly string $password,
    ) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        $name = $this->organization->name ?? config('app.name');

        return new Envelope(
            subject: "Votre acces au portail alumni de {$name}",
        );
    }

    /**
     * The message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.alumni-credentials',
            with: [
                'alumni' => $this->alumni,
                'organizationName' => $this->organization->name ?? config('app.name'),
                'password' => $this->password,
                'loginUrl' => $this->organization === null
                    ? url('/')
                    : route('portal.login', ['organization' => $this->organization->slug]),
                'expiresDays' => config('portal.initial_password_expires_days'),
            ],
        );
    }
}
