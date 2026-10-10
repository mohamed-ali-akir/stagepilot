<?php

namespace App\Http\Resources;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One application as returned by the API (the JSON "shape" is decided here, not in the controller).
 *
 * @mixin JobApplication
 */
class JobApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'priority' => $this->priority,
            'distance_km' => $this->distance_km,
            'hook' => $this->hook,
            'proof' => $this->proof_excerpt ? ['excerpt' => $this->proof_excerpt, 'url' => $this->proof_url] : null,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'followed_up_at' => $this->followed_up_at?->toIso8601String(),
            'replies_count' => $this->whenCounted('replies'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
