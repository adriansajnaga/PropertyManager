<?php

namespace Tests\Feature;

use App\Enums\SettlementStatus;
use App\Enums\UtilityType;
use App\Mail\TenantSettlementMail;
use App\Models\TenantSettlement;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBill;
use App\Services\HeatSettlementCalculator;
use App\Services\TenantSettlementCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettlementDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private TenantSettlement $settlement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create(['email' => 'biuro@example.com']));

        $month = CarbonImmutable::parse('2026-02-01');
        app(HeatSettlementCalculator::class)->store($month, UtilityBill::where('type', UtilityType::Electric)->first());

        $this->settlement = app(TenantSettlementCalculator::class)
            ->store(Unit::where('description', 'Lokal 12')->first(), $month);
    }

    public function test_the_pdf_fits_on_a_single_a4_page(): void
    {
        $this->settlement->update(['status' => SettlementStatus::Final]);

        $pdf = $this->get(route('tenant-settlements.pdf', $this->settlement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf), 'Rozliczenie ma mieścić się na jednej stronie.');
    }

    public function test_the_pdf_shows_how_the_price_per_gj_was_calculated(): void
    {
        $pdf = $this->get(route('tenant-settlements.pdf', $this->settlement))->getContent();

        // Treść PDF jest skompresowana, więc sprawdzamy sam szablon.
        $html = view('pdf.tenant-settlement', [
            'settlement' => $this->settlement->load('lines', 'unit.property', 'tenant', 'waterBill', 'electricBill'),
            'heatSettlement' => \App\Models\HeatSettlement::with('lines')->first(),
            'maintenanceCosts' => $this->settlement->unit->property->maintenanceCostsForYear(2026)->get(),
        ])->render();

        $this->assertStringContainsString('Koszt 1 GJ energii cieplnej netto', $html);
        $this->assertStringContainsString('Łączne zużycie energii elektrycznej przez pompę ciepła', $html);
        $this->assertStringContainsString('PM Property Manager, ASCOMM Adrian Sajnaga', $html);
        $this->assertNotEmpty($pdf);
    }

    /**
     * @return array<string, string>
     */
    private function message(array $overrides = []): array
    {
        return array_merge([
            'email' => 'najemca@example.com',
            'subject' => TenantSettlementMail::defaultSubject($this->settlement),
            'body' => TenantSettlementMail::defaultBody($this->settlement),
        ], $overrides);
    }

    public function test_a_draft_settlement_cannot_be_emailed(): void
    {
        Mail::fake();

        $this->post(route('tenant-settlements.email', $this->settlement), $this->message())
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_an_approved_settlement_is_emailed_with_the_pdf_attached(): void
    {
        Mail::fake();
        $this->settlement->update(['status' => SettlementStatus::Final]);

        $this->post(route('tenant-settlements.email', $this->settlement), $this->message())
            ->assertRedirect();

        Mail::assertSent(TenantSettlementMail::class, function (TenantSettlementMail $mail) {
            return $mail->hasTo('najemca@example.com')
                && $mail->attachments() !== []
                && $mail->settlement->is($this->settlement);
        });
    }

    public function test_the_subject_and_message_can_be_edited_before_sending(): void
    {
        Mail::fake();
        $this->settlement->update(['status' => SettlementStatus::Final]);

        $this->post(route('tenant-settlements.email', $this->settlement), $this->message([
            'subject' => 'Rozliczenie za luty — do zapłaty do 15.03',
            'body' => "Dzień dobry,\n\nw załączniku rozliczenie. Termin płatności: 15.03.2026.",
        ]))->assertRedirect();

        Mail::assertSent(TenantSettlementMail::class, function (TenantSettlementMail $mail) {
            $rendered = $mail->render();

            return $mail->envelope()->subject === 'Rozliczenie za luty — do zapłaty do 15.03'
                && str_contains($rendered, 'Termin płatności: 15.03.2026');
        });
    }

    public function test_an_empty_message_is_refused(): void
    {
        Mail::fake();
        $this->settlement->update(['status' => SettlementStatus::Final]);

        $this->post(route('tenant-settlements.email', $this->settlement), $this->message(['body' => '']))
            ->assertSessionHasErrors('body');

        Mail::assertNothingSent();
    }

    public function test_the_form_suggests_the_logged_in_users_address(): void
    {
        $this->settlement->update(['status' => SettlementStatus::Final]);

        $this->get(route('tenant-settlements.show', $this->settlement))
            ->assertOk()
            ->assertSee('Wyślij rozliczenie e-mailem')
            ->assertSee('biuro@example.com');
    }
}
