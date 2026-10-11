<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Mail\ReadingWatchdogMail;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Kontrola dziennych odczytów wywoływana z crona: milczenie licznika ma dać
 * e-mail, komplet odczytów — ciszę.
 */
class ReadingWatchdogTest extends TestCase
{
    use RefreshDatabase;

    private Meter $electric;

    private Meter $water;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-11 21:00');
        Mail::fake();

        User::factory()->admin()->create(['email' => 'admin@przyklad.test']);

        $this->electric = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'EL-1',
            'name' => 'Prąd budynek',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->water = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'WO-1',
            'name' => 'Woda lokal 12',
            'is_active' => true,
            'is_main' => false,
        ]);
    }

    private function reading(Meter $meter, string $at): Reading
    {
        return Reading::create([
            'meter_id' => $meter->id,
            'source_table' => $meter->type->value,
            'source_meter_serial' => $meter->serial_number,
            'consumption' => 100,
            'reading_date' => $at,
        ]);
    }

    private function ping()
    {
        return $this->get(URL::signedRoute('monitoring.readings'));
    }

    public function test_the_link_is_refused_without_a_signature(): void
    {
        $this->get(route('monitoring.readings'))->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_a_full_set_of_readings_sends_nothing(): void
    {
        $this->reading($this->electric, '2026-10-11 06:00');
        $this->reading($this->water, '2026-10-11 06:05');

        $this->ping()
            ->assertOk()
            ->assertSee('OK 2026-10-11: 2 z 2');

        Mail::assertNothingSent();
    }

    public function test_no_readings_at_all_sends_an_alert(): void
    {
        $this->ping()->assertOk()->assertSee('BRAKI 2026-10-11: 2 z 2');

        Mail::assertSent(ReadingWatchdogMail::class, function (ReadingWatchdogMail $mail) {
            return $mail->hasTo('admin@przyklad.test')
                && $mail->reported === 0
                && $mail->missing->count() === 2
                && str_contains($mail->subjectLine(), 'Brak odczytów z 11.10.2026');
        });
    }

    public function test_an_incomplete_set_names_the_missing_meters(): void
    {
        $this->reading($this->electric, '2026-10-11 06:00');
        // Wodomierz milczy od wczoraj.
        $this->reading($this->water, '2026-10-10 06:05');

        $this->ping()->assertOk()->assertSee('BRAKI 2026-10-11: 1 z 2');

        Mail::assertSent(ReadingWatchdogMail::class, function (ReadingWatchdogMail $mail) {
            return $mail->missing->pluck('name')->all() === ['Woda lokal 12']
                && str_contains($mail->subjectLine(), 'Brakuje 1 z 2')
                && str_contains($mail->render(), 'Woda lokal 12')
                && str_contains($mail->render(), '10.10.2026, 06:05');
        });
    }

    public function test_the_same_shortage_is_not_reported_twice_the_same_day(): void
    {
        $this->reading($this->electric, '2026-10-11 06:00');

        $this->ping()->assertOk();
        $this->ping()->assertOk()->assertSee('wiadomość już poszła');

        Mail::assertSentCount(1);
    }

    public function test_a_changed_shortage_is_reported_again(): void
    {
        $this->reading($this->electric, '2026-10-11 06:00');
        $this->ping();

        // Doszedł trzeci licznik, który też milczy — lista braków jest inna.
        Meter::create([
            'type' => MeterType::Heat,
            'serial_number' => 'CI-1',
            'name' => 'Ciepło kotłownia',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->ping()->assertOk();

        Mail::assertSentCount(2);
    }

    public function test_modules_and_inactive_meters_are_not_expected_to_report(): void
    {
        $this->reading($this->electric, '2026-10-11 06:00');
        $this->reading($this->water, '2026-10-11 06:05');

        Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'NAK-1',
            'name' => 'Nakładka wody 12',
            'is_active' => true,
            'is_main' => false,
            'is_module' => true,
            'module_for_meter_id' => $this->water->id,
            'module_offset' => 0,
        ]);

        Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'EL-STARY',
            'name' => 'Prąd zdemontowany',
            'is_active' => false,
            'is_main' => false,
        ]);

        $this->ping()->assertOk()->assertSee('OK 2026-10-11: 2 z 2');

        Mail::assertNothingSent();
    }
}
