<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantOperationRun extends RegistryModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'action',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'triggered_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'triggered_by_user_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
