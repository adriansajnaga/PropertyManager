<?php

namespace App\Models;

use App\Enums\KsefEnvironment;
use Illuminate\Database\Eloquent\Model;

class KsefSetting extends Model
{
    protected $fillable = [
        'environment',
        'nip',
        'token',
        'verified_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'environment' => KsefEnvironment::class,
            'token' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::first() ?? new static(['environment' => KsefEnvironment::Test]);
    }

    public function isConfigured(): bool
    {
        return filled($this->nip) && filled($this->token);
    }

    public function baseUrl(): string
    {
        return ($this->environment ?? KsefEnvironment::Test)->baseUrl();
    }
}
