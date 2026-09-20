<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'total_area',
    ];

    protected function casts(): array
    {
        return [
            'total_area' => 'decimal:2',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function maintenanceCosts(): HasMany
    {
        return $this->hasMany(MaintenanceCost::class);
    }

    public function maintenanceCostsForYear(int $year): HasMany
    {
        return $this->maintenanceCosts()->where('year', $year);
    }
}
