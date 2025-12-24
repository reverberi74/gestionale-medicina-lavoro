<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'billing_period' => $this->billing_period,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'is_active' => (bool) $this->is_active,
            'features' => $this->features,
            'limits' => $this->limits,
        ];
    }
}
