<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CallLogResource extends JsonResource
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
            'called_at' => $this->called_at,
            'transcript' => $this->transcript,
            'recording_path' => $this->recording_path,
            'recording_url' => $this->recording_path
                ? Storage::disk('public')->url($this->recording_path)
                : null,
            'notes' => $this->notes,
            'clarity' => $this->clarity,
            'energy_tone' => $this->energy_tone,
            'composure_on_pushback' => $this->composure_on_pushback,
            'overall_gut_check' => $this->overall_gut_check,
            'created_at' => $this->created_at,
        ];
    }
}
