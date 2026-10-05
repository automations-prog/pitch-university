<?php

namespace App\Models;

use App\Enums\CallRating;
use Database\Factories\CallLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screening_response_id
 * @property Carbon|null $called_at
 * @property string|null $transcript
 * @property string|null $recording_path
 * @property string|null $notes
 * @property CallRating|null $clarity
 * @property CallRating|null $energy_tone
 * @property CallRating|null $composure_on_pushback
 * @property CallRating|null $overall_gut_check
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['called_at', 'transcript', 'recording_path', 'notes', 'clarity', 'energy_tone', 'composure_on_pushback', 'overall_gut_check'])]
class CallLog extends Model
{
    /** @use HasFactory<CallLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'clarity' => CallRating::class,
            'energy_tone' => CallRating::class,
            'composure_on_pushback' => CallRating::class,
            'overall_gut_check' => CallRating::class,
        ];
    }

    /**
     * Calls the AI voice agent has completed that an admin has not rated yet.
     *
     * @param  Builder<CallLog>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->whereNotNull('called_at')->whereNull('overall_gut_check');
    }

    public function screeningResponse(): BelongsTo
    {
        return $this->belongsTo(ScreeningResponse::class);
    }
}
