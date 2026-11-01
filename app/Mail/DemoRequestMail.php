<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Request a Demo" form on the public homepage, sent to the ERPSaathi team. */
class DemoRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{school_name: string, phone: string, submitted_at: string, page: string, ip: string|null}  $details
     */
    public function __construct(public array $details) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New demo request - '.$this->details['school_name'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.demo-request',
        );
    }
}
