<?php

namespace App\Mail;

use App\Models\TenantSettlement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantSettlementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantSettlement $settlement,
        private readonly string $pdf,
        private readonly string $filename,
        public readonly ?string $body = null,
        private readonly ?string $customSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->customSubject ?: self::defaultSubject($this->settlement));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tenant-settlement', with: [
            'body' => $this->body ?: self::defaultBody($this->settlement),
        ]);
    }

    public static function defaultSubject(TenantSettlement $settlement): string
    {
        return sprintf(
            'Rozliczenie %s — %s, %s',
            $settlement->month->isoFormat('MMMM YYYY'),
            $settlement->unit->description,
            $settlement->unit->property->name,
        );
    }

    public static function defaultBody(TenantSettlement $settlement): string
    {
        return sprintf(
            "Dzień dobry,\n\nw załączniku przesyłamy rozliczenie mediów i kosztów eksploatacyjnych za %s ".
            "dla lokalu %s przy %s.\n\nProsimy o zapoznanie się z dokumentem. W razie pytań prosimy o kontakt.",
            $settlement->month->isoFormat('MMMM YYYY'),
            $settlement->unit->description,
            $settlement->unit->property->address,
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
