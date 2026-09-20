<?php

namespace App\Models;

use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UtilityBill extends Model
{
    protected $fillable = [
        'type',
        'invoice_number',
        'net_price',
        'base_net_price',
        'margin_percent',
        'consumption_value',
        'net_amount',
        'month',
    ];

    protected function casts(): array
    {
        return [
            'type' => UtilityType::class,
            'net_price' => 'decimal:4',
            'base_net_price' => 'decimal:4',
            'margin_percent' => 'decimal:2',
            'consumption_value' => 'decimal:4',
            'net_amount' => 'decimal:2',
            'month' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Cena obowiązująca = cena z faktury powiększona o marżę. Liczona przy każdym
        // zapisie, więc rozliczenia zawsze korzystają z aktualnej wartości.
        static::saving(function (UtilityBill $bill) {
            $bill->base_net_price ??= $bill->net_price;
            $bill->net_price = round((float) $bill->base_net_price * (1 + (float) $bill->margin_percent / 100), 4);
        });
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        return $query->whereDate('month', $month);
    }
}
