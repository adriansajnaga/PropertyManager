<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nip',
        'email',
        'phone',
        'city',
        'zip',
        'street',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UnitTenantAssignment::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(TenantSettlement::class);
    }

    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function currentUnits()
    {
        $today = now()->toDateString();

        return $this->assignments()
            ->with('unit.property')
            ->where('valid_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->get()
            ->pluck('unit')
            ->filter();
    }

    public function fullAddress(): string
    {
        return trim(sprintf('%s, %s %s', $this->street, $this->zip, $this->city), ' ,');
    }
}
