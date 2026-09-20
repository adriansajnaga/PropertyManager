<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitTenantAssignment extends Model
{
    protected $fillable = [
        'unit_id',
        'tenant_id',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
