<?php

namespace Database\Factories;

use App\Models\CallLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallLog>
 *
 * Note: every ScreeningResponse auto-creates its own CallLog (see
 * ScreeningResponse::booted()), so this factory intentionally has no
 * default `screening_response_id` — a CallLog is always reached via
 * `$screeningResponse->callLog`, never created standalone.
 */
class CallLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'called_at' => null,
            'transcript' => null,
            'recording_path' => null,
            'notes' => null,
        ];
    }
}
