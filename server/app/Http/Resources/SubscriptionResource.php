<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'current_period_start_at' => optional($this->current_period_start_at)?->toISOString(),
            'current_period_end_at' => optional($this->current_period_end_at)?->toISOString(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'provider' => $this->provider,
            'provider_ref' => $this->provider_ref,
            'meta' => $this->meta,
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
        ];
    }
}
