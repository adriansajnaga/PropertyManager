<?php

namespace Tests\Feature;

use App\Mail\RentReminderMail;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentAccrualService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-15');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function tenantWithCharges(): Tenant
    {
        $unit = Unit::where('description', 'Lokal 12')->firstOrFail();
        $unit->update(['rent_amount' => 2500]);

        app(RentAccrualService::class)->run();

        return $unit->currentTenant();
    }

    public function test_a_tenant_keeps_an_email_address(): void
    {
        $tenant = Tenant::first();

        $this->put(route('tenants.update', $tenant), [
            'name' => $tenant->name,
            'email' => 'biuro@najemca.example',
            'phone' => '600 100 200',
            'is_active' => '1',
        ])->assertRedirect(route('tenants.show', $tenant));

        $tenant->refresh();

        $this->assertSame('biuro@najemca.example', $tenant->email);
        $this->assertSame('600 100 200', $tenant->phone);
    }

    public function test_the_tenant_page_shows_the_payment_summary(): void
    {
        $tenant = $this->tenantWithCharges();

        $this->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Płatności czynszu')
            ->assertSee('7 500,00 zł'); // trzy miesiące po 2 500 zł
    }

    public function test_the_page_of_a_tenant_without_any_charges_opens(): void
    {
        $tenant = Tenant::create(['name' => 'Nowy Najemca sp. z o.o.', 'is_active' => true]);

        $this->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Brak naliczeń czynszu');
    }

    public function test_the_annual_summary_is_a_pdf(): void
    {
        $tenant = $this->tenantWithCharges();

        $pdf = $this->get(route('tenants.payments.pdf', ['tenant' => $tenant, 'year' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertNotEmpty($pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_a_reminder_goes_out_with_the_outstanding_months(): void
    {
        Mail::fake();
        $tenant = $this->tenantWithCharges();
        $tenant->update(['email' => 'biuro@najemca.example']);

        $charges = $tenant->rentCharges()->get();
        $this->assertGreaterThan(0, $charges->count());

        $this->post(route('tenants.payments.reminder', $tenant), [
            'email' => $tenant->email,
            'subject' => RentReminderMail::defaultSubject($tenant),
            'body' => 'Dzień dobry, prosimy o uregulowanie zaległości.',
        ])->assertRedirect();

        Mail::assertSent(RentReminderMail::class, function (RentReminderMail $mail) {
            return $mail->hasTo('biuro@najemca.example')
                && str_contains($mail->render(), 'styczeń 2026')
                && str_contains($mail->render(), 'Dzień dobry, prosimy o uregulowanie zaległości.');
        });
    }

    public function test_a_tenant_without_arrears_gets_no_reminder(): void
    {
        Mail::fake();
        $tenant = $this->tenantWithCharges();
        $tenant->rentCharges()->update(['paid_on' => '2026-03-01']);

        $this->post(route('tenants.payments.reminder', $tenant), [
            'email' => 'biuro@najemca.example',
            'subject' => 'Przypomnienie',
            'body' => 'Treść',
        ])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }
}
