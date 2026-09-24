<?php

namespace App\Models;

use Database\Factories\ScreeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token
 * @property string|null $voice
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['token', 'voice'])]
class Screening extends Model
{
    /** @use HasFactory<ScreeningFactory> */
    use HasFactory;

    public function responses(): HasMany
    {
        return $this->hasMany(ScreeningResponse::class);
    }
}
