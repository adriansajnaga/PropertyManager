<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $primaryKey = 'source_table';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'source_table',
        'last_synced_id',
        'last_run_at',
        'last_success_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_id' => 'integer',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function isStale(): bool
    {
        $hours = (int) config('pm.sync.stale_after_hours');

        return $this->last_success_at === null
            || $this->last_success_at->lt(now()->subHours($hours));
    }
}
