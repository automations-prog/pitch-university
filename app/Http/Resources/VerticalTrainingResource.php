<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerticalTrainingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'license_id' => $this->license_id,
            'license' => LicenseResource::make($this->whenLoaded('license')),
            'script_title' => $this->script_title,
            'script_scenario' => $this->script_scenario,
            'script_body' => $this->script_body,
            'has_script' => $this->hasScript(),
            'created_at' => $this->created_at,
        ];
    }
}
