<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the platform team about a landing page contact request. Replying answers the prospect directly.
 */
class DemoRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new instance.
     */
    public function __construct(public readonly DemoRequest $demo) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->demo->email, $this->demo->name)],
            subject: "{$this->demo->subjectLabel()} : {$this->demo->organization}",
        );
    }

    /**
     * The message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.demo-requested',
            with: ['demo' => $this->demo],
        );
    }
}
