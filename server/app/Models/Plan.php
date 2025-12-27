<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $connection = 'registry';

    protected $fillable = [
        'code',
        'name',
        'billing_period',
        'price_cents',
        'currency',
        'is_active',
        'features',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'features' => 'array',
            'limits' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
