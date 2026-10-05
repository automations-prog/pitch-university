<?php

namespace App\Http\Resources;

use App\Models\RoleplaySession;
use App\Services\RoleplayScript;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoleplaySession
 */
class RoleplaySessionResource extends JsonResource
{
    /**
     * Transform the resource into an array. The persona's hidden parts
     * (outcome, objections, DQ trap) are only included once the call has
     * ended.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isEnded = $this->ended_at !== null;

        return [
            'id' => $this->id,
            'level' => $this->level,
            'lead' => $this->persona['lead'],
            'created_at' => $this->created_at,
            'ended_at' => $this->ended_at,
            'disposition' => $this->disposition,
            'passed' => $this->passed,
            $this->mergeWhen($isEnded, fn (): array => [
                'expected_outcome' => $this->expected_outcome,
                // What the consumer did can change the right code (a
                // second busy, a DNC request); older calls only have the
                // persona's outcome.
                'correct_disposition' => $this->score['correct_disposition'] ?? $this->expected_outcome->value,
                'correct_reason' => $this->score['correct_reason'] ?? null,
                'end_reason' => $this->end_reason,
                'checks' => $this->score['checks'] ?? [],
                'delivery_status' => $this->delivery_status,
                'delivery' => $this->delivery,
                'dq_trap' => $this->persona['dq_trap'],
                'objections' => array_values(array_filter(array_map(
                    fn (string $id): ?array => app(RoleplayScript::class)->objection($id),
                    $this->persona['objections'],
                ))),
                'transcript' => $this->transcript,
                'recording_url' => $this->recording_path === null
                    ? null
                    : route('roleplay.sessions.recording', $this->resource),
            ]),
        ];
    }
}
