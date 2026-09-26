<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        private readonly string $pdf,
        private readonly string $filename,
        public readonly ?string $body = null,
        private readonly ?string $customSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->customSubject ?: self::defaultSubject($this->invoice));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: [
            'body' => $this->body ?: self::defaultBody($this->invoice),
        ]);
    }

    public static function defaultSubject(Invoice $invoice): string
    {
        return $invoice->title();
    }

    public static function defaultBody(Invoice $invoice): string
    {
        return sprintf(
            "Dzień dobry,\n\nw załączniku przesyłamy %s na kwotę %s zł.\n".
            "Termin płatności: %s.\n\nW razie pytań prosimy o kontakt.",
            $invoice->isReceipt() ? 'rachunek' : 'fakturę',
            Format::money($invoice->total_gross),
            $invoice->due_on->format('d.m.Y'),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf'),
        ];
    }
}
