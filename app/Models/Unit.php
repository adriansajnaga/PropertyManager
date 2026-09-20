<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'description',
        'area',
        'rent_amount',
        'deposit_amount',
        'deposit_paid_on',
        'settles_utilities',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_paid_on' => 'date',
            'rent_accrued_through' => 'date',
            'settles_utilities' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenantAssignments(): HasMany
    {
        return $this->hasMany(UnitTenantAssignment::class);
    }

    public function meterAssignments(): HasMany
    {
        return $this->hasMany(UnitMeterAssignment::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(TenantSettlement::class);
    }

    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UnitDocument::class);
    }

    public function depositPaid(): bool
    {
        return $this->deposit_paid_on !== null;
    }

    public function currentTenant(): ?Tenant
    {
        return $this->tenantAt(now()->toDateString());
    }

    public function tenantAt(string $date): ?Tenant
    {
        $assignment = $this->tenantAssignments()
            ->with('tenant')
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();

        return $assignment?->tenant;
    }

    public function currentMeters()
    {
        return $this->metersAt(now()->toDateString());
    }

    public function metersAt(string $date)
    {
        return $this->meterAssignments()
            ->with('meter')
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->get()
            ->pluck('meter')
            ->filter();
    }

    public function meterAt(string $date, MeterType $type): ?Meter
    {
        return $this->metersAt($date)->firstWhere('type', $type);
    }
}
