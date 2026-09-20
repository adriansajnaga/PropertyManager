<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceCost extends Model
{
    protected $fillable = [
        'property_id',
        'description',
        'annual_cost',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'annual_cost' => 'decimal:2',
            'year' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
