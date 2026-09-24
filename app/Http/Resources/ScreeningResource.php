<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningResource extends JsonResource
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
            'token' => $this->token,
            'voice' => $this->voice,
            'public_url' => route('screening.show', $this->token),
            'responses_count' => $this->whenCounted('responses'),
            'created_at' => $this->created_at,
        ];
    }
}
