<?php

namespace App\Models;

use Database\Factories\ScreeningResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screening_id
 * @property string $full_name
 * @property string $email
 * @property string $phone_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['full_name', 'email', 'phone_number'])]
class ScreeningResponse extends Model
{
    /** @use HasFactory<ScreeningResponseFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (ScreeningResponse $screeningResponse): void {
            $screeningResponse->callLog()->create([]);
        });
    }

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    public function callLog(): HasOne
    {
        return $this->hasOne(CallLog::class);
    }
}
