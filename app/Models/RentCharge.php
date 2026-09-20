<?php

namespace App\Models;

use App\Enums\RentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentCharge extends Model
{
    protected $fillable = [
        'unit_id',
        'tenant_id',
        'month',
        'amount',
        'invoice_number',
        'due_on',
        'paid_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'decimal:2',
            'status' => RentStatus::class,
            'due_on' => 'date',
            'paid_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RentCharge $charge) {
            // Miesiąc identyfikuje czynsz, więc zapisujemy zawsze pierwszy dzień.
            $charge->month = $charge->month?->startOfMonth();

            // Status wynika z danych: faktura czyni z niego wystawiony, zapłata — zapłacony.
            $charge->status = match (true) {
                $charge->paid_on !== null => RentStatus::Paid,
                filled($charge->invoice_number) => RentStatus::Issued,
                default => RentStatus::Draft,
            };
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === RentStatus::Draft;
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('paid_on');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereNotNull('paid_on');
    }

    /**
     * Zaległość to brak zapłaty po terminie z faktury, a gdy faktury jeszcze nie ma —
     * po zakończeniu miesiąca, którego czynsz dotyczy. Szkic za bieżący miesiąc nie alarmuje.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->where(function (Builder $query) {
            $query->whereDate('due_on', '<', now()->toDateString())
                ->orWhere(fn (Builder $q) => $q->whereNull('due_on')
                    ->whereDate('month', '<', now()->startOfMonth()->toDateString()));
        });
    }

    public function isOverdue(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $this->due_on
            ? $this->due_on->isBefore(now()->startOfDay())
            : $this->month->isBefore(now()->startOfMonth());
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereBetween('month', [$year.'-01-01', $year.'-12-31']);
    }
}
