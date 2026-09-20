<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const VALID = [
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'biuro@example.com',
        'password' => 'tajne-haslo',
        'from_address' => 'biuro@example.com',
        'from_name' => 'Sajnaga Property Manager',
    ];

    public function test_only_an_admin_reaches_the_mail_settings(): void
    {
        $this->actingAs(User::factory()->create())->get(route('mail-settings.edit'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('mail-settings.edit'))->assertOk();
    }

    public function test_settings_are_saved_and_the_password_is_encrypted_in_the_database(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('mail-settings.update'), self::VALID)
            ->assertRedirect(route('mail-settings.edit'));

        $settings = MailSetting::current();
        $this->assertSame('smtp.example.com', $settings->host);
        $this->assertSame('tajne-haslo', $settings->password);

        $stored = DB::table('mail_settings')->value('password');
        $this->assertNotSame('tajne-haslo', $stored, 'Hasło nie może leżeć w bazie otwartym tekstem.');
    }

    public function test_an_empty_password_field_keeps_the_previous_one(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('mail-settings.update'), self::VALID);
        $this->actingAs($admin)->put(route('mail-settings.update'), [...self::VALID, 'password' => '']);

        $this->assertSame('tajne-haslo', MailSetting::current()->password);
    }

    public function test_saved_settings_replace_the_configuration_from_env(): void
    {
        MailSetting::create(self::VALID);

        MailSetting::current()->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('biuro@example.com', config('mail.from.address'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'), 'STARTTLS na 587 to zwykłe smtp.');
    }

    public function test_port_465_uses_implicit_tls(): void
    {
        MailSetting::create([...self::VALID, 'port' => 465, 'encryption' => 'ssl']);

        MailSetting::current()->apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
    }

    public function test_a_test_message_is_refused_before_the_server_is_configured(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('mail-settings.test'), ['email' => 'ja@example.com'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_a_test_message_is_sent_once_the_server_is_configured(): void
    {
        Mail::fake();
        MailSetting::create(self::VALID);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('mail-settings.test'), ['email' => 'ja@example.com'])
            ->assertRedirect();

        Mail::assertSentCount(1);
    }
}
