<?php

namespace App\Mail;

use App\Models\Meter;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Wiadomość kontroli odczytów — wysyłana tylko wtedy, gdy czegoś brakuje.
 */
class ReadingWatchdogMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Meter>  $missing
     * @param  array<int, string>  $lastSeen  ostatni odczyt licznika, po jego id
     */
    public function __construct(
        public readonly CarbonInterface $day,
        public readonly Collection $missing,
        public readonly int $expected,
        public readonly int $reported,
        public readonly int $orphans,
        public readonly array $lastSeen = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reading-watchdog');
    }

    public function subjectLine(): string
    {
        return $this->reported === 0
            ? 'Brak odczytów z '.$this->day->format('d.m.Y')
            : sprintf(
                'Brakuje %d z %d odczytów z %s',
                $this->missing->count(),
                $this->expected,
                $this->day->format('d.m.Y'),
            );
    }
}
