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
use Illuminate\Support\Facades\Http;
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
            'channels' => ['email'],
            'email' => $tenant->email,
            'subject' => RentReminderMail::defaultSubject($tenant),
            'body' => 'Dzień dobry, prosimy o uregulowanie zaległości.',
        ])->assertRedirect();

        Mail::assertSent(RentReminderMail::class, function (RentReminderMail $mail) {
            return $mail->hasTo('biuro@najemca.example')
                && str_contains($mail->render(), 'styczeń 2026')
                && str_contains($mail->render(), 'Dzień dobry, prosimy o uregulowanie zaległości.')
                && str_contains($mail->render(), 'Wiadomość wygenerowana automatycznie');
        });
    }

    public function test_a_broken_mail_server_shows_a_message_instead_of_a_crash(): void
    {
        $tenant = $this->tenantWithCharges();

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Connection could not be established'));

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['email'],
            'email' => 'biuro@najemca.example',
            'subject' => 'Przypomnienie',
            'body' => 'Treść',
        ])->assertRedirect()->assertSessionHasErrors('email');
    }

    private function smsGatewayReady(array $response = ['count' => 1, 'list' => [['id' => '1', 'status' => 'QUEUE']]]): void
    {
        config(['services.smsapi.token' => 'test-token', 'services.smsapi.sender' => null, 'services.smsapi.test' => false]);

        Http::fake(['api.smsapi.pl/*' => Http::response($response)]);
    }

    public function test_a_reminder_goes_out_as_an_sms_through_smsapi(): void
    {
        $this->smsGatewayReady();
        $tenant = $this->tenantWithCharges();

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['sms'],
            'phone' => '600 100 200',
            'sms_message' => 'Przypomnienie: brak wpłaty czynszu.',
        ])->assertRedirect()->assertSessionHas('status', 'Przypomnienie wysłane: SMS na +48 600 100 200.');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.smsapi.pl/sms.do'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '48600100200'
            && $request['message'] === 'Przypomnienie: brak wpłaty czynszu.'
            && $request['normalize'] === '1');
    }

    public function test_in_test_mode_the_app_says_plainly_that_nothing_was_sent(): void
    {
        $this->smsGatewayReady();
        config(['services.smsapi.test' => true]);
        $tenant = $this->tenantWithCharges();

        $this->get(route('tenants.show', $tenant))->assertSee('Tryb testowy bramki SMS');

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['sms'],
            'phone' => '600100200',
            'sms_message' => 'Przypomnienie SMS',
        ])->assertSessionHas('status', fn ($status) => str_contains($status, 'NIE został wysłany'));

        Http::assertSent(fn ($request) => $request['test'] === '1');
    }

    public function test_email_and_sms_go_out_together(): void
    {
        Mail::fake();
        $this->smsGatewayReady();
        $tenant = $this->tenantWithCharges();

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['email', 'sms'],
            'email' => 'biuro@najemca.example',
            'subject' => 'Przypomnienie',
            'body' => 'Treść',
            'phone' => '+48 600-100-200',
            'sms_message' => 'Przypomnienie SMS',
        ])->assertSessionHas('status', 'Przypomnienie wysłane: e-mail na biuro@najemca.example oraz SMS na +48 600 100 200.');

        Mail::assertSent(RentReminderMail::class);
        Http::assertSentCount(1);
    }

    public function test_a_rejected_sms_does_not_stop_the_email_and_is_offered_again(): void
    {
        Mail::fake();
        // SMSAPI zgłasza błędy w treści odpowiedzi, nawet przy statusie HTTP 200.
        $this->smsGatewayReady(['error' => 101, 'message' => 'Authorization failed']);
        $tenant = $this->tenantWithCharges();

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['email', 'sms'],
            'email' => 'biuro@najemca.example',
            'subject' => 'Przypomnienie',
            'body' => 'Treść',
            'phone' => '600100200',
            'sms_message' => 'Przypomnienie SMS',
        ])
            ->assertSessionHas('status', 'Przypomnienie wysłane: e-mail na biuro@najemca.example.')
            ->assertSessionHasErrors('phone')
            ->assertSessionHasInput('channels', ['sms']);

        Mail::assertSent(RentReminderMail::class);
    }

    public function test_an_invalid_phone_number_is_refused(): void
    {
        $this->smsGatewayReady();
        $tenant = $this->tenantWithCharges();

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['sms'],
            'phone' => '123',
            'sms_message' => 'Przypomnienie SMS',
        ])->assertSessionHasErrors('phone');

        Http::assertNothingSent();
    }

    public function test_without_a_token_the_sms_is_refused_with_an_explanation(): void
    {
        config(['services.smsapi.token' => null]);
        Http::fake();
        $tenant = $this->tenantWithCharges();

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['sms'],
            'phone' => '600100200',
            'sms_message' => 'Przypomnienie SMS',
        ])->assertSessionHasErrors(['phone' => 'SMS nie został wysłany: Bramka SMS nie jest skonfigurowana — brak SMSAPI_TOKEN w pliku .env.']);

        Http::assertNothingSent();
    }

    public function test_a_reminder_needs_at_least_one_channel(): void
    {
        $tenant = $this->tenantWithCharges();

        $this->post(route('tenants.payments.reminder', $tenant), [])
            ->assertSessionHasErrors('channels');
    }

    public function test_a_tenant_without_arrears_gets_no_reminder(): void
    {
        Mail::fake();
        $tenant = $this->tenantWithCharges();
        $tenant->rentCharges()->update(['paid_on' => '2026-03-01']);

        $this->post(route('tenants.payments.reminder', $tenant), [
            'channels' => ['email'],
            'email' => 'biuro@najemca.example',
            'subject' => 'Przypomnienie',
            'body' => 'Treść',
        ])->assertSessionHasErrors('channels');

        Mail::assertNothingSent();
    }
}
