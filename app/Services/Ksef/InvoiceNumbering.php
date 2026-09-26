<?php

namespace App\Services\Ksef;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\KsefSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Numer faktury to „kolejny/miesiąc/rok" (np. 3/9/2026). Kolejny numer musi
 * uwzględniać faktury wystawione poza aplikacją, dlatego przed nadaniem numeru
 * pytamy KSeF o dokumenty z tego miesiąca i bierzemy najwyższy zajęty numer.
 */
class InvoiceNumbering
{
    public function __construct(private readonly KsefClient $client) {}

    /**
     * @return array{number: string, highest: int, warning: ?string}
     */
    public function next(CarbonInterface $issuedOn, DocumentType $type = DocumentType::Invoice): array
    {
        $month = CarbonImmutable::parse($issuedOn);
        $warning = null;
        $highest = $this->highestLocally($month, $type);

        // Rachunki są tylko w aplikacji, więc ich numeracji KSeF nie zna.
        if ($type->goesToKsef() && KsefSetting::current()->isConfigured()) {
            try {
                $highest = max($highest, $this->highestInKsef($month));
            } catch (Throwable $e) {
                $warning = 'Nie udało się sprawdzić numeracji w KSeF: '.$e->getMessage()
                    .' Numer nadano na podstawie faktur w aplikacji — sprawdź go przed wysyłką.';
            }
        }

        return [
            'number' => ($highest + 1).'/'.$month->month.'/'.$month->year,
            'highest' => $highest,
            'warning' => $warning,
        ];
    }

    private function highestLocally(CarbonImmutable $month, DocumentType $type): int
    {
        return Invoice::query()
            ->where('document_type', $type)
            ->whereYear('issued_on', $month->year)
            ->whereMonth('issued_on', $month->month)
            ->pluck('number')
            ->map(fn ($number) => $this->position((string) $number, $month))
            ->push(0)
            ->max();
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
