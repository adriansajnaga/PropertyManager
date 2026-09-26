<?php

namespace App\Services\Ksef;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\KsefSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Numer faktury to „kolejny/miesiąc/rok" (np. 3/9/2026). O kolejny numer pyta się
 * wyłącznie KSeF — to on jest rejestrem faktur, także tych wystawionych poza
 * aplikacją. Lokalna baza nie bierze udziału w liczeniu, bo sama pochodzi z importu
 * z KSeF, a dokumenty z innego środowiska w ogóle nie należą do tej serii.
 * Rachunki imienne KSeF-u nie dotyczą i mają własny, lokalny licznik.
 */
class InvoiceNumbering
{
    public function __construct(private readonly KsefClient $client) {}

    /**
     * @return array{number: string, highest: int, warning: ?string}
     *
     * @throws KsefException gdy KSeF nie odpowiada — numeru nie zgadujemy
     */
    public function next(CarbonInterface $issuedOn, DocumentType $type = DocumentType::Invoice): array
    {
        $month = CarbonImmutable::parse($issuedOn);

        if (! $type->goesToKsef()) {
            return $this->fromApplication($month, $type);
        }

        if (! KsefSetting::current()->isConfigured()) {
            throw new KsefException(
                'Numer faktury nadaje KSeF, a połączenie nie jest skonfigurowane. '
                .'Uzupełnij ustawienia KSeF w Administracji.'
            );
        }

        try {
            $highest = $this->highestInKsef($month);
        } catch (Throwable $e) {
            throw new KsefException(
                'Nie udało się pobrać numeracji z KSeF: '.$e->getMessage()
                .' Faktury nie numerujemy na własną rękę — spróbuj ponownie za chwilę.'
            );
        }

        $number = ($highest + 1).'/'.$month->month.'/'.$month->year;

        $this->refuseIfTaken($number);

        return [
            'number' => $number,
            'highest' => $highest,
            'warning' => null,
        ];
    }

    /** Rachunki: numeracja z własnych dokumentów, bo nie ma ich w żadnym rejestrze. */
    private function fromApplication(CarbonImmutable $month, DocumentType $type): array
    {
        $highest = Invoice::query()
            ->where('document_type', $type)
            ->whereYear('issued_on', $month->year)
            ->whereMonth('issued_on', $month->month)
            ->pluck('number')
            ->map(fn ($number) => $this->position((string) $number, $month))
            ->push(0)
            ->max();

        return [
            'number' => ($highest + 1).'/'.$month->month.'/'.$month->year,
            'highest' => $highest,
            'warning' => null,
        ];
    }

    /**
     * KSeF nie wie o fakturze, która czeka w aplikacji na wysyłkę, więc podałby
     * numer już zajęty. Nie liczymy wtedy po swojemu — wstrzymujemy wystawienie,
     * bo najpierw ten dokument ma trafić do rejestru.
     */
    private function refuseIfTaken(string $number): void
    {
        $taken = Invoice::query()
            ->where('document_type', DocumentType::Invoice)
            ->where('number', $number)
            ->first();

        if ($taken === null) {
            return;
        }

        throw new KsefException($taken->ksef_number === null
            ? "KSeF podał numer {$number}, a w aplikacji czeka już faktura o tym numerze, "
                .'której tam nie ma. Wyślij ją najpierw do KSeF.'
            : "KSeF podał numer {$number}, a faktura o tym numerze jest już w aplikacji — "
                .'rejestr jeszcze jej nie pokazuje. Pobierz listę faktur z KSeF i spróbuj ponownie.');
    }

    private function highestInKsef(CarbonImmutable $month): int
    {
        $highest = 0;
        $offset = 0;

        do {
            $page = $this->client->queryInvoiceMetadata(
                $month->startOfMonth(),
                $month->endOfMonth(),
                'Subject1',
                $offset,
            );

            foreach ($page['invoices'] as $metadata) {
                $highest = max($highest, $this->position((string) ($metadata['invoiceNumber'] ?? ''), $month));
            }

            $offset += count($page['invoices']);
        } while ($page['hasMore'] && $page['invoices'] !== []);

        return $highest;
    }

    /** Numer liczy się tylko wtedy, gdy dotyczy tego samego miesiąca i roku. */
    private function position(string $number, CarbonImmutable $month): int
    {
        if (! preg_match('#^(\d+)/(\d+)/(\d{4})$#', trim($number), $matches)) {
            return 0;
        }

        return (int) $matches[2] === $month->month && (int) $matches[3] === $month->year
            ? (int) $matches[1]
            : 0;
    }
}
