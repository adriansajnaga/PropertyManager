<?php

namespace Tests\Feature;

use App\Enums\KsefEnvironment;
use App\Models\KsefSetting;
use App\Models\User;
use App\Services\Ksef\KsefClient;
use App\Services\Ksef\KsefException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\File\X509;
use phpseclib3\Math\BigInteger;
use Tests\TestCase;

/**
 * Uwierzytelnianie w KSeF 2.0 tokenem — przebieg zgodny z dokumentacją
 * Ministerstwa Finansów (CIRFMF/ksef-api).
 */
class KsefConnectionTest extends TestCase
{
    use RefreshDatabase;

    private PrivateKey $ksefKey;

    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());

        [$this->ksefKey, $this->certificate] = $this->fakeKsefCertificate();

        KsefSetting::create([
            'environment' => KsefEnvironment::Test,
            'nip' => '9221122333',
            'token' => 'TOKEN-KSEF-XYZ',
        ]);
    }

    /** Certyfikat udający ten publikowany przez KSeF — generowany w czystym PHP. */
    private function fakeKsefCertificate(): array
    {
        $key = RSA::createKey(2048);

        $subject = new X509;
        $subject->setPublicKey($key->getPublicKey());
        $subject->setDN(['CN' => 'Ministerstwo Finansow']);

        $issuer = new X509;
        $issuer->setPrivateKey($key);
        $issuer->setDN($subject->getDN());

        $authority = new X509;
        $authority->setSerialNumber(new BigInteger(1), 10);
        $authority->setStartDate('-1 day');
        $authority->setEndDate('+1 year');

        $pem = $authority->saveX509($authority->sign($issuer, $subject));

        return [$key, preg_replace('/-----[^-]+-----|\s+/', '', $pem)];
    }

    private function fakeKsef(array $overrides = []): void
    {
        Http::fake(array_merge([
            '*/security/public-key-certificates' => Http::response([
                [
                    'certificate' => $this->certificate,
                    'certificateId' => 'cert-1',
                    'publicKeyId' => 'klucz-1',
                    'usage' => ['SymmetricKeyEncryption'],
                ],
                [
                    'certificate' => $this->certificate,
                    'certificateId' => 'cert-2',
                    'publicKeyId' => 'klucz-2',
                    'usage' => ['KsefTokenEncryption'],
                ],
            ]),
            '*/auth/challenge' => Http::response([
                'challenge' => '20260925-CR-1234567890',
                'timestamp' => '2026-09-25T08:00:00Z',
                'timestampMs' => 1790323200000,
            ]),
            '*/auth/ksef-token' => Http::response([
                'referenceNumber' => 'REF-001',
                'authenticationToken' => ['token' => 'OPERACYJNY', 'validUntil' => '2026-09-25T09:00:00Z'],
            ], 202),
            '*/auth/REF-001' => Http::response([
                'status' => ['code' => 200, 'description' => 'Uwierzytelnianie zakończone sukcesem'],
            ]),
            '*/auth/token/redeem' => Http::response([
                'accessToken' => ['token' => 'DOSTEPOWY', 'validUntil' => now()->addHour()->toIso8601String()],
                'refreshToken' => ['token' => 'ODSWIEZAJACY'],
            ]),
            '*/auth/sessions/current' => Http::response([
                'contextIdentifier' => ['type' => 'Nip', 'value' => '9221122333'],
            ]),
        ], $overrides));
    }

    public function test_it_encrypts_the_token_exactly_as_ksef_expects(): void
    {
        $this->fakeKsef();

        $token = KsefClient::forCurrentSettings()->accessToken();

        $this->assertSame('DOSTEPOWY', $token);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/auth/ksef-token')) {
                return false;
            }

            // KSeF oczekuje „token|timestampMs" zaszyfrowanego RSA-OAEP z SHA-256.
            $plain = $this->ksefKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->decrypt(base64_decode($request['encryptedToken']));

            return $plain === 'TOKEN-KSEF-XYZ|1790323200000'
                && $request['challenge'] === '20260925-CR-1234567890'
                && $request['contextIdentifier'] === ['type' => 'Nip', 'value' => '9221122333']
                && $request['publicKeyId'] === 'klucz-2';
        });
    }

    public function test_it_uses_the_test_environment_address(): void
    {
        $this->fakeKsef();

        KsefClient::forCurrentSettings()->accessToken();

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api-test.ksef.mf.gov.pl/v2/'));
    }

    public function test_the_access_token_is_reused_instead_of_authenticating_again(): void
    {
        $this->fakeKsef();

        $client = KsefClient::forCurrentSettings();
        $client->accessToken();
        $client->accessToken();

        // klucz publiczny, challenge, ksef-token, status, redeem — jeden komplet, nie dwa
        Http::assertSentCount(5);
    }

    public function test_a_rejected_authentication_explains_why(): void
    {
        $this->fakeKsef([
            '*/auth/REF-001' => Http::response([
                'status' => ['code' => 400, 'description' => 'Token nieaktywny'],
            ]),
        ]);

        $this->expectException(KsefException::class);
        $this->expectExceptionMessage('Token nieaktywny');

        KsefClient::forCurrentSettings()->accessToken();
    }

    public function test_an_api_error_is_translated_into_a_readable_message(): void
    {
        $this->fakeKsef([
            '*/auth/challenge' => Http::response([
                'exception' => ['exceptionDetailList' => [['exceptionDescription' => 'Nieprawidłowy NIP']]],
            ], 400),
        ]);

        $this->expectException(KsefException::class);
        $this->expectExceptionMessage('Nieprawidłowy NIP');

        KsefClient::forCurrentSettings()->accessToken();
    }

    public function test_the_settings_screen_saves_the_token_encrypted_and_never_shows_it(): void
    {
        $this->put(route('ksef-settings.update'), [
            'environment' => 'demo',
            'nip' => '9221122333',
            'token' => 'NOWY-TOKEN',
        ])->assertRedirect();

        $settings = KsefSetting::first();

        $this->assertSame(KsefEnvironment::Demo, $settings->environment);
        $this->assertSame('NOWY-TOKEN', $settings->token);
        $this->assertStringNotContainsString('NOWY-TOKEN', $settings->getRawOriginal('token'));

        $this->get(route('ksef-settings.edit'))->assertOk()->assertDontSee('NOWY-TOKEN');
    }

    public function test_the_settings_can_also_be_saved_with_a_plain_post(): void
    {
        // Część serwerów gubi ukryte pole `_method`, więc trasa przyjmuje oba sposoby.
        $this->post(route('ksef-settings.update'), [
            'environment' => 'prod',
            'nip' => '9221122333',
            'token' => 'PRODUKCYJNY',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(\App\Enums\KsefEnvironment::Prod, KsefSetting::first()->environment);
    }

    public function test_an_empty_token_field_keeps_the_saved_one(): void
    {
        $this->put(route('ksef-settings.update'), [
            'environment' => 'test',
            'nip' => '9221122333',
            'token' => '',
        ])->assertRedirect();

        $this->assertSame('TOKEN-KSEF-XYZ', KsefSetting::first()->token);
    }

    public function test_the_connection_check_confirms_the_context_and_remembers_the_date(): void
    {
        $this->fakeKsef();

        $this->post(route('ksef-settings.test'))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'działa')
                && str_contains($status, '9221122333'));

        $this->assertNotNull(KsefSetting::first()->verified_at);
    }

    public function test_a_failed_check_shows_the_reason_instead_of_crashing(): void
    {
        $this->fakeKsef([
            '*/auth/challenge' => Http::response(['message' => 'Serwis niedostępny'], 503),
        ]);

        $this->post(route('ksef-settings.test'))->assertSessionHasErrors('token');

        $this->assertNull(KsefSetting::first()->verified_at);
    }

    public function test_the_module_is_only_for_administrators(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('ksef-settings.edit'))->assertForbidden();
    }
}
