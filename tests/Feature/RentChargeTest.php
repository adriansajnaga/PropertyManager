<?php

namespace Tests\Feature;

use App\Enums\RentStatus;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentAccrualService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Najem w danych demo zaczyna się 01.01.2026 — zamrożenie czasu daje
        // przewidywalną liczbę naliczonych miesięcy.
        $this->travelTo('2026-03-15');

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function unit(string $description = 'Lokal 12'): Unit
    {
        return Unit::where('description', $description)->firstOrFail();
    }

    public function test_the_unit_stores_rent_and_deposit(): void
    {
        $unit = $this->unit();

        $this->put(route('units.update', $unit), [
            'property_id' => $unit->property_id,
            'description' => $unit->description,
            'area' => $unit->area,
            'rent_amount' => 2500,
            'deposit_amount' => 5000,
            'deposit_paid_on' => '2026-01-15',
        ])->assertRedirect(route('units.show', $unit));

        $unit->refresh();

        $this->assertSame('2500.00', $unit->rent_amount);
        $this->assertSame('5000.00', $unit->deposit_amount);
        $this->assertTrue($unit->depositPaid());
        $this->assertSame('2026-01-15', $unit->deposit_paid_on->toDateString());
    }

    public function test_rent_is_accrued_for_every_month_of_the_tenancy_up_to_the_current_one(): void
    {
        $this->unit()->update(['rent_amount' => 2500]);

        app(RentAccrualService::class)->run();

        $charges = $this->unit()->rentCharges()->orderBy('month')->get();

        $this->assertSame(['2026-01-01', '2026-02-01', '2026-03-01'], $charges->map->month->map->toDateString()->all());
        $this->assertSame('2500.00', $charges->first()->amount);
        $this->assertSame(RentStatus::Draft, $charges->first()->status);
        $this->assertSame($this->unit()->currentTenant()->id, $charges->first()->tenant_id);
    }

    public function test_a_unit_without_a_rate_or_without_a_tenant_is_not_charged(): void
    {
        // Lokal 18 nie ma najemcy w danych demo, Lokal 14 nie ma stawki.
        $this->unit('Lokal 18')->update(['rent_amount' => 3000]);

        app(RentAccrualService::class)->run();

        $this->assertSame(0, $this->unit('Lokal 18')->rentCharges()->count());
        $this->assertSame(0, $this->unit('Lokal 14')->rentCharges()->count());
    }

    public function test_repeated_accrual_adds_nothing_and_a_deleted_charge_does_not_come_back(): void
    {
        $this->unit()->update(['rent_amount' => 2500]);
        $accrual = app(RentAccrualService::class);

        $this->assertSame(3, $accrual->run());
        $this->assertSame(0, $accrual->run());

        $this->unit()->rentCharges()->whereDate('month', '2026-02-01')->delete();

        $this->assertSame(0, $accrual->run());
        $this->assertSame(2, $this->unit()->rentCharges()->count());
    }

    public function test_opening_the_list_accrues_the_missing_months(): void
    {
        $this->unit()->update(['rent_amount' => 2500]);

        $this->get(route('rent-charges.index'))->assertOk()->assertSee('szkic');

        $this->assertSame(3, $this->unit()->rentCharges()->count());
    }

    public function test_an_invoice_number_turns_a_draft_into_an_issued_charge(): void
    {
        $this->unit()->update(['rent_amount' => 2500]);
        app(RentAccrualService::class)->run();

        $charge = $this->unit()->rentCharges()->whereDate('month', '2026-03-01')->firstOrFail();

        $this->put(route('rent-charges.update', $charge), [
            'unit_id' => $charge->unit_id,
            'month' => '2026-03',
            'amount' => 2500,
            'invoice_number' => 'FV/12/03/2026',
            'due_on' => '2026-03-25',
        ])->assertRedirect();

        $charge->refresh();

        $this->assertSame(RentStatus::Issued, $charge->status);
        $this->assertSame('FV/12/03/2026', $charge->invoice_number);
        $this->assertSame('2026-03-25', $charge->due_on->toDateString());
        $this->assertFalse($charge->isPaid());
    }

    public function test_the_list_button_confirms_payment_with_todays_date_and_takes_it_back(): void
    {
        $this->unit()->update(['rent_amount' => 2500]);
        app(RentAccrualService::class)->run();

        $charge = $this->unit()->rentCharges()->whereDate('month', '2026-03-01')->firstOrFail();

        $this->from(route('rent-charges.index'))
            ->post(route('rent-charges.paid', $charge))
            ->assertRedirect(route('rent-charges.index'));

        $charge->refresh();

        $this->assertSame('2026-03-15', $charge->paid_on->toDateString());
        $this->assertSame(RentStatus::Paid, $charge->status);

        $this->post(route('rent-charges.paid', $charge));

        $this->assertFalse($charge->refresh()->isPaid());
    }

    public function test_unchecking_the_paid_box_clears_the_payment_date(): void
    {
        $charge = RentCharge::create([
            'unit_id' => $this->unit()->id,
            'month' => '2026-02-01',
            'amount' => 2500,
            'invoice_number' => 'FV/12/02/2026',
            'paid_on' => '2026-02-10',
        ]);

        $this->put(route('rent-charges.update', $charge), [
            'unit_id' => $charge->unit_id,
            'month' => '2026-02',
            'amount' => 2500,
            'invoice_number' => 'FV/12/02/2026',
            'paid_on' => '2026-02-10',
        ])->assertRedirect();

        $charge->refresh();

        $this->assertFalse($charge->isPaid());
        $this->assertSame(RentStatus::Issued, $charge->status);
    }

    public function test_the_same_month_cannot_be_charged_twice_for_one_unit(): void
    {
        $unit = $this->unit();

        RentCharge::create(['unit_id' => $unit->id, 'month' => '2026-02-01', 'amount' => 2500]);

        $this->post(route('rent-charges.store'), [
            'unit_id' => $unit->id,
            'month' => '2026-02',
            'amount' => 2500,
        ])->assertSessionHasErrors('month');

        $this->assertSame(1, RentCharge::count());
    }

    public function test_an_unpaid_charge_from_a_closed_month_shows_up_on_the_dashboard(): void
    {
        $this->unit()->update(['rent_amount' => 2500]);

        $this->get(route('overview'))->assertOk()->assertSee('Niezapłacony czynsz');

        // Szkic za bieżący miesiąc jeszcze nie jest zaległością — liczą się styczeń i luty.
        $this->assertSame(2, RentCharge::overdue()->count());
    }

    public function test_a_rate_added_after_an_earlier_run_is_still_charged(): void
    {
        $accrual = app(RentAccrualService::class);

        // Pierwszy przebieg na lokalu bez stawki nie może zablokować naliczenia,
        // gdy stawka i najemca pojawią się później.
        $this->assertSame(0, $accrual->run());

        $this->unit()->update(['rent_amount' => 2500]);

        $this->assertSame(3, $accrual->run());
    }

    public function test_the_rent_pages_render(): void
    {
        $charge = RentCharge::create([
            'unit_id' => $this->unit()->id,
            'month' => '2026-02-01',
            'amount' => 2500,
            'invoice_number' => 'FV/12/02/2026',
        ]);

        $this->get(route('rent-charges.index', ['year' => 2026]))->assertOk()->assertSee('FV/12/02/2026');
        $this->get(route('rent-charges.create', ['unit_id' => $this->unit()->id]))->assertOk();
        $this->get(route('rent-charges.edit', $charge))->assertOk();
        $this->get(route('units.show', $this->unit()))->assertOk()->assertSee('Czynsz');
    }
}
