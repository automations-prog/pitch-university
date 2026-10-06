<?php

namespace App\Models;

use App\Enums\Outcome;
use App\Enums\RealtimeVoice;
use Database\Factories\RoleplaySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $level
 * @property array{lead: array{name: string, state: string, zip: string}, objections: list<string>, quirk: string|null, patience: int, dq_trap: array{id: string, hidden_truth: string}|null} $persona
 * @property Outcome $expected_outcome
 * @property RealtimeVoice|null $voice
 * @property string|null $disposition
 * @property string|null $end_reason
 * @property string|null $transcript
 * @property list<array<string, mixed>>|null $events
 * @property string|null $recording_path
 * @property array<string, mixed>|null $score
 * @property array{criteria: list<array{key: string, label: string, score: int|null, source: string, feedback: string}>, metrics: array<string, mixed>, overall: float|null}|null $delivery
 * @property string|null $delivery_status
 * @property bool|null $passed
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['level', 'persona', 'expected_outcome', 'voice', 'disposition', 'end_reason', 'transcript', 'events', 'recording_path', 'score', 'delivery', 'delivery_status', 'passed', 'started_at', 'ended_at'])]
class RoleplaySession extends Model
{
    /** @use HasFactory<RoleplaySessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'persona' => 'array',
            'expected_outcome' => Outcome::class,
            'voice' => RealtimeVoice::class,
            'events' => 'array',
            'score' => 'array',
            'delivery' => 'array',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The part of the persona the trainee may see before the call ends: no
     * objections, outcome or DQ trap.
     *
     * @return array{id: int, level: int, lead: array{name: string, state: string, zip: string}, quirk: string|null, patience: int}
     */
    public function publicPersona(): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'lead' => $this->persona['lead'],
            'quirk' => $this->persona['quirk'],
            'patience' => $this->persona['patience'],
        ];
    }

    /**
     * The lead's gender, falling back to the stored voice's for personas
     * saved without one.
     *
     * @return 'male'|'female'|null
     */
    public function consumerGender(): ?string
    {
        $gender = $this->persona['lead']['gender'] ?? null;

        return in_array($gender, ['male', 'female'], true) ? $gender : $this->voice?->gender();
    }

    /**
     * The voice the consumer speaks in, always plainly of the lead's
     * gender: a stored voice of the other gender, one that could pass for
     * either, or none, is replaced with that gender's default.
     */
    public function consumerVoice(): ?RealtimeVoice
    {
        $gender = $this->consumerGender();

        if ($gender === null) {
            return $this->voice;
        }

        if ($this->voice?->gender() === $gender && $this->voice->isClearlyGendered()) {
            return $this->voice;
        }

        return RealtimeVoice::defaultFor($gender);
    }
}
