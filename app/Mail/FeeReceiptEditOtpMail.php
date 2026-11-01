<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One-time code that approves an edit to a saved fee receipt (sent to the school's email). */
class FeeReceiptEditOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{school: string, receipt_no: string, student: string, admission_no: string, editor: string, reason: string, before_total: float, after_total: float, changes: list<string>, minutes: int}  $details
     */
    public function __construct(public string $otp, public array $details) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OTP '.$this->otp.' to approve edit of receipt '.$this->details['receipt_no'].' - '.$this->details['school'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'erp.emails.fee-receipt-edit-otp',
        );
    }
}
