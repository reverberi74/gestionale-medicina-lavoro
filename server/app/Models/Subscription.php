<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends RegistryModel
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'current_period_start_at',
        'current_period_end_at',
        'cancel_at_period_end',
        'provider',
        'provider_ref',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start_at' => 'datetime',
            'current_period_end_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
