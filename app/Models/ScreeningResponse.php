<?php

namespace App\Models;

use Database\Factories\ScreeningResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screening_id
 * @property string $full_name
 * @property string $email
 * @property Carbon $birthday
 * @property string $phone_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['full_name', 'email', 'birthday', 'phone_number'])]
class ScreeningResponse extends Model
{
    /** @use HasFactory<ScreeningResponseFactory> */
    use HasFactory;

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }
}
