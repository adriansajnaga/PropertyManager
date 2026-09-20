<?php

namespace App\Mail;

use App\Models\RentCharge;
use App\Models\Tenant;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, RentCharge>  $charges
     */
    public function __construct(
        public Tenant $tenant,
        public Collection $charges,
        public readonly ?string $body = null,
        private readonly ?string $customSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->customSubject ?: self::defaultSubject($this->tenant));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rent-reminder', with: [
            'body' => $this->body ?: self::defaultBody($this->tenant, $this->charges),
            'total' => $this->total(),
        ]);
    }

    public static function defaultSubject(Tenant $tenant): string
    {
        return 'Przypomnienie o płatności czynszu — '.$tenant->name;
    }

    /**
     * @param  Collection<int, RentCharge>  $charges
     */
    public static function defaultBody(Tenant $tenant, Collection $charges): string
    {
        $months = $charges
            ->map(fn (RentCharge $charge) => $charge->month->isoFormat('MMMM YYYY'))
            ->unique()
            ->implode(', ');

        $total = Format::money($charges->sum(fn (RentCharge $charge) => (float) $charge->amount));

        return sprintf(
            "Dzień dobry,\n\nw naszej ewidencji nie odnotowaliśmy wpłaty czynszu za: %s.\n".
            "Łączna kwota zaległości to %s zł.\n\n".
            "Jeżeli płatność została już wykonana, prosimy o potwierdzenie i pominięcie tej wiadomości.",
            $months !== '' ? $months : 'wskazane miesiące',
            $total,
        );
    }

    public function total(): float
    {
        return round($this->charges->sum(fn (RentCharge $charge) => (float) $charge->amount), 2);
    }
}
