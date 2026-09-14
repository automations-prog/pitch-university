<?php

namespace App\Models;

use Database\Factories\LicenseStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $license_id
 * @property string $title
 * @property string|null $description
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'order'])]
class LicenseStep extends Model
{
    /** @use HasFactory<LicenseStepFactory> */
    use HasFactory;

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
