<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentInvoiceFactory;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakesKsefApi;
use Tests\TestCase;

/**
 * Rachunek imienny — dokument na czas zawieszonej działalności: bez VAT,
 * bez KSeF, w osobnej serii numerów. Oraz wysyłka dokumentu najemcy e-mailem.
 */
class ReceiptAndDocumentEmailTest extends TestCase
{
    use FakesKsefApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $this->configureKsef();
        $this->fakeKsefSending();

        InvoiceSetting::create([
            'seller_name' => 'ASCOMM Adrian Sajnaga',
            'seller_nip' => '8792451081',
            'seller_address_l1' => 'ul. Konstytucji 3 Maja 15/12',
            'seller_address_l2' => '87-100 Toruń',
            'bank_account' => '87 1160 2202 0000 0002 5233 8336',
            'issue_place' => 'Toruń',
            'payment_days' => 7,
            'vat_rate' => 23,
            'rent_is_gross' => true,
            'receipt_issuer_name' => 'Adrian Sajnaga',
            'receipt_address_l1' => 'ul. Konstytucji 3 Maja 15/12',
            'receipt_address_l2' => '87-100 Toruń',
            'receipt_note' => 'Działalność zawieszona — rachunek bez VAT.',
        ]);

        Unit::where('description', 'Lokal 12')->firstOrFail()
            ->currentTenant()
            ->update(['nip' => '8792688305', 'email' => 'biuro@najemca.example']);
    }

    private function charge(string $unitName = 'Lokal 12', float $amount = 1230): RentCharge
    {
        $unit = Unit::where('description', $unitName)->firstOrFail();

        return RentCharge::create([
            'unit_id' => $unit->id,
            'tenant_id' => $unit->currentTenant()->id,
            'month' => '2026-09-01',
            'amount' => $amount,
        ]);
    }

    public function test_a_receipt_carries_the_whole_amount_without_vat(): void
    {
        $receipt = app(RentInvoiceFactory::class)->fromRentCharge($this->charge(), null, DocumentType::Receipt);

        $this->assertSame(DocumentType::Receipt, $receipt->document_type);
        $this->assertSame('1230.00', $receipt->total_net);
        $this->assertSame('0.00', $receipt->total_vat);
        $this->assertSame('1230.00', $receipt->total_gross);
        $this->assertSame('Rachunek 1/9/2026', $receipt->title());
    }

    public function test_receipts_and_invoices_have_separate_numbering(): void
    {
        $factory = app(RentInvoiceFactory::class);

        $invoice = $factory->fromRentCharge($this->charge('Lokal 12'), null, DocumentType::Invoice);
        $receipt = $factory->fromRentCharge($this->charge('Lokal 14'), null, DocumentType::Receipt);

        $this->assertSame('1/9/2026', $invoice->number);
        $this->assertSame('1/9/2026', $receipt->number);
        $this->assertNotSame($invoice->id, $receipt->id);
    }

    public function test_one_rent_charge_takes_either_an_invoice_or_a_receipt(): void
    {
        $charge = $this->charge();

        app(RentInvoiceFactory::class)->fromRentCharge($charge, null, DocumentType::Receipt);

        $this->get(route('invoices.create', ['rent_charge_id' => $charge->id, 'document_type' => 'invoice']))
            ->assertRedirect(route('invoices.index'))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::count());
    }

    public function test_a_receipt_is_never_sent_to_ksef(): void
    {
        $receipt = app(RentInvoiceFactory::class)->fromRentCharge($this->charge(), null, DocumentType::Receipt);

        $this->from(route('invoices.show', $receipt))
            ->post(route('invoices.send', $receipt))
            ->assertSessionHasErrors('invoice');

        $this->get(route('invoices.show', $receipt))->assertOk()->assertDontSee('Wyślij do KSeF');
    }

    public function test_without_issuer_data_a_receipt_is_refused(): void
    {
        InvoiceSetting::current()->update(['receipt_issuer_name' => null]);

        $this->get(route('invoices.create', ['rent_charge_id' => $this->charge()->id, 'document_type' => 'receipt']))
            ->assertRedirect(route('invoices.index'))
            ->assertSessionHasErrors('invoice');
    }

    public function test_the_receipt_pdf_shows_the_issuer_and_no_vat_column(): void
    {
        $receipt = app(RentInvoiceFactory::class)->fromRentCharge($this->charge(), null, DocumentType::Receipt);

        $html = view('pdf.invoice', [
            'invoice' => $receipt->load('lines', 'tenant'),
            'settings' => InvoiceSetting::current(),
            'qrUrl' => null,
            'qrCode' => null,
        ])->render();

        $this->assertStringContainsString('RACHUNEK 1/9/2026', $html);
        $this->assertStringContainsString('Wystawca:', $html);
        $this->assertStringContainsString('Adrian Sajnaga', $html);
        $this->assertStringContainsString('Działalność zawieszona', $html);
        // Rachunek nie ma kolumny ani podsumowania podatku.
        $this->assertStringNotContainsString('Razem VAT', $html);

        $this->get(route('invoices.pdf', $receipt))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_document_can_have_more_than_one_line(): void
    {
        $charge = $this->charge();

        $this->post(route('invoices.store'), [
            'rent_charge_id' => $charge->id,
            'document_type' => 'invoice',
            'number' => '7/9/2026',
            'issued_on' => '2026-09-26',
            'sold_on' => '2026-09-26',
            'due_on' => '2026-10-03',
            'vat_rate' => 23,
            'lines' => [
                ['name' => 'Czynsz wrzesień 2026', 'unit' => 'szt.', 'quantity' => 1, 'unit_price_net' => 1000],
                ['name' => 'Woda wrzesień 2026', 'unit' => 'm³', 'quantity' => 2.5, 'unit_price_net' => 12],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice = Invoice::where('number', '7/9/2026')->sole();

        $this->assertCount(2, $invoice->lines);
        $this->assertSame('1030.00', $invoice->total_net);   // 1000 + 30
        $this->assertSame('236.90', $invoice->total_vat);
        $this->assertSame('1266.90', $invoice->total_gross);
        $this->assertSame(2, $invoice->lines->last()->position);
    }

    public function test_the_document_goes_to_the_tenant_by_email_with_the_pdf_attached(): void
    {
        Mail::fake();
        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge());

        $this->post(route('invoices.email', $invoice), [
            'email' => 'biuro@najemca.example',
            'subject' => InvoiceMail::defaultSubject($invoice),
            'body' => 'Dzień dobry, w załączniku faktura za wrzesień.',
        ])->assertRedirect()->assertSessionHas('status', fn ($status) => str_contains($status, 'biuro@najemca.example'));

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
            $attachment = $mail->attachments()[0] ?? null;

            return $mail->hasTo('biuro@najemca.example')
                && $mail->invoice->is($invoice)
                && $attachment !== null
                && str_contains($mail->render(), 'w załączniku faktura za wrzesień');
        });
    }

    public function test_the_email_form_suggests_the_tenant_address(): void
    {
        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge());

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Wyślij fakturę najemcy')
            ->assertSee('biuro@najemca.example');
    }

    public function test_an_empty_message_is_refused(): void
    {
        Mail::fake();
        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge());

        $this->from(route('invoices.show', $invoice))
            ->post(route('invoices.email', $invoice), [
                'email' => 'biuro@najemca.example',
                'subject' => 'Faktura',
                'body' => '',
            ])->assertSessionHasErrors('body');

        Mail::assertNothingSent();
    }
}
