<?php

namespace App\Models;

use App\Enums\VerticalTrainingStatus;
use Database\Factories\VerticalTrainingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property VerticalTrainingStatus $status
 * @property string|null $script_title
 * @property string|null $script_scenario
 * @property string|null $script_body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'status', 'script_title', 'script_scenario', 'script_body'])]
class VerticalTraining extends Model
{
    /** @use HasFactory<VerticalTrainingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VerticalTrainingStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === VerticalTrainingStatus::Active;
    }

    /**
     * Whether a roleplay script has been written for this training.
     */
    public function hasScript(): bool
    {
        return filled($this->script_title);
    }
}
