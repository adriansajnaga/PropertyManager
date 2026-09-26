<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\KsefEnvironment;
use App\Models\Invoice;
use App\Services\Ksef\InvoiceNumbering;
use App\Services\Ksef\KsefException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesKsefApi;
use Tests\TestCase;

/**
 * Numer faktury nadaje KSeF i tylko KSeF: jest kolejny po wszystkich dokumentach
 * danego miesiąca, także tych wystawionych poza aplikacją. Lokalna baza nie liczy,
 * bo sama pochodzi z importu — a gdy rejestr milczy, faktury nie wystawiamy.
 */
class InvoiceNumberingTest extends TestCase
{
    use FakesKsefApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26');
        $this->configureKsef();
    }

    private function ksefHas(string ...$numbers): void
    {
        $this->fakeKsefSending([
            '*/invoices/query/metadata*' => Http::response([
                'invoices' => array_map(fn ($number) => ['invoiceNumber' => $number], $numbers),
                'hasMore' => false,
            ]),
        ]);
    }

    private function next(): array
    {
        return app(InvoiceNumbering::class)->next(CarbonImmutable::parse('2026-09-26'));
    }

    public function test_the_first_invoice_of_the_month_gets_number_one(): void
    {
        $this->ksefHas();

        $this->assertSame('1/9/2026', $this->next()['number']);
    }

    public function test_it_continues_after_the_highest_number_found_in_ksef(): void
    {
        $this->ksefHas('1/9/2026', '2/9/2026', '3/9/2026', '4/9/2026', '5/9/2026');

        $this->assertSame('6/9/2026', $this->next()['number']);
    }

    public function test_a_single_invoice_in_ksef_pushes_the_number_to_two(): void
    {
        $this->ksefHas('1/9/2026');

        $this->assertSame('2/9/2026', $this->next()['number']);
    }

    public function test_numbers_from_other_months_do_not_count(): void
    {
        $this->ksefHas('7/8/2026', '9/9/2025', 'FV/2026/123');

        $this->assertSame('1/9/2026', $this->next()['number']);
    }

    public function test_documents_in_the_application_do_not_raise_the_number(): void
    {
        $this->ksefHas('1/9/2026');

        // Faktura zaimportowana z innego środowiska ani rachunek imienny nie należą
        // do tej serii — numer wynika wyłącznie z odpowiedzi rejestru.
        $this->invoice('7/9/2026', KsefEnvironment::Demo);
        $this->invoice('9/9/2026', null, DocumentType::Receipt);

        $this->assertSame('2/9/2026', $this->next()['number']);
    }

    public function test_an_invoice_waiting_for_ksef_stops_the_next_one(): void
    {
        $this->ksefHas();

        // 1/9/2026 czeka w aplikacji, więc rejestr podałby numer już zajęty.
        $this->invoice('1/9/2026');

        $this->expectException(KsefException::class);
        $this->expectExceptionMessage('Wyślij ją najpierw do KSeF.');

        $this->next();
    }

    public function test_a_number_the_register_has_not_caught_up_with_stops_the_next_one(): void
    {
        $this->ksefHas();

        $this->invoice('1/9/2026', KsefEnvironment::Test);

        $this->expectException(KsefException::class);
        $this->expectExceptionMessage('Pobierz listę faktur z KSeF');

        $this->next();
    }

    public function test_without_an_answer_from_ksef_no_number_is_given(): void
    {
        $this->fakeKsefSending([
            '*/invoices/query/metadata*' => Http::response(['message' => 'Serwis niedostępny'], 503),
        ]);

        $this->expectException(KsefException::class);
        $this->expectExceptionMessage('Faktury nie numerujemy na własną rękę');

        $this->next();
    }

    public function test_receipts_keep_their_own_numbering_without_ksef(): void
    {
        Http::fake();

        $this->invoice('1/9/2026', null, DocumentType::Receipt);

        $next = app(InvoiceNumbering::class)
            ->next(CarbonImmutable::parse('2026-09-26'), DocumentType::Receipt);

        $this->assertSame('2/9/2026', $next['number']);
        Http::assertNothingSent();
    }

    private function invoice(
        string $number,
        ?KsefEnvironment $environment = null,
        DocumentType $type = DocumentType::Invoice,
    ): Invoice {
        return Invoice::create([
            'number' => $number,
            'document_type' => $type,
            'issued_on' => '2026-09-20',
            'sold_on' => '2026-09-20',
            'due_on' => '2026-09-27',
            'vat_rate' => 23,
            'total_net' => 100,
            'total_vat' => 23,
            'total_gross' => 123,
            'ksef_number' => $environment ? '8792451081-20260920-AAA-01' : null,
            'ksef_environment' => $environment,
        ]);
    }
}
