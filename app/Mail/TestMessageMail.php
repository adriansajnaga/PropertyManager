<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Wiadomość testowa — '.config('pm.report_issuer'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test-message');
    }
}
