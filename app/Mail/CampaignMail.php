<?php

namespace App\Mail;

use App\Models\Alumni;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One campaign e-mail to one alumni (docs/project.md F5).
 */
class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly Alumni $alumni,
        public readonly string $organizationName,
    ) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    /**
     * The message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.campaign',
            with: [
                'campaign' => $this->campaign,
                'alumni' => $this->alumni,
                'organizationName' => $this->organizationName,
            ],
        );
    }
}
