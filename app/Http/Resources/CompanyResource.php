<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company as returned by the API: only what the student needs, never internal fields.
 *
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'siren' => $this->siren,
            'name' => $this->name,
            'activity' => $this->activity,
            'naf_code' => $this->naf_code,
            'city' => $this->city,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'do_not_contact' => $this->blocked,
        ];
    }
}
