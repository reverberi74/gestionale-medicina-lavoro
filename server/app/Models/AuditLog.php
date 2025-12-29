<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends RegistryModel
{
    protected $table = 'audit_logs';

    /**
     * Table has only created_at (default CURRENT_TIMESTAMP).
     * Disable Eloquent timestamps to avoid expecting updated_at.
     */
    public $timestamps = false;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    /**
     * Hardening: allow only explicit mass assignment.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'method',
        'path',
        'status_code',
        'ip',
        'host',
        'user_agent',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'status_code' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
