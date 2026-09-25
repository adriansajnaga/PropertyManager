<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\Ksef\InvoiceNumbering;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesKsefApi;
use Tests\TestCase;

/**
 * Numer faktury musi być kolejny po wszystkich dokumentach danego miesiąca —
 * także tych wystawionych poza aplikacją, bo te widać tylko w KSeF.
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

    public function test_invoices_issued_in_the_application_count_too(): void
    {
        $this->ksefHas('1/9/2026');

        Invoice::create([
            'number' => '4/9/2026',
            'issued_on' => '2026-09-20',
            'sold_on' => '2026-09-20',
            'due_on' => '2026-09-27',
            'vat_rate' => 23,
            'total_net' => 100,
            'total_vat' => 23,
            'total_gross' => 123,
        ]);

        $this->assertSame('5/9/2026', $this->next()['number']);
    }

    public function test_when_ksef_is_unreachable_the_number_comes_with_a_warning(): void
    {
        $this->fakeKsefSending([
            '*/invoices/query/metadata*' => Http::response(['message' => 'Serwis niedostępny'], 503),
        ]);

        $result = $this->next();

        $this->assertSame('1/9/2026', $result['number']);
        $this->assertStringContainsString('Nie udało się sprawdzić numeracji w KSeF', $result['warning']);
    }
}
