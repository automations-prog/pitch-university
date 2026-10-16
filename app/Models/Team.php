<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_id', 'name'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Everyone on the team, its admin included.
     *
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * The admin who runs the team: the one admin whose own team it is.
     *
     * @return HasOne<User, $this>
     */
    public function manager(): HasOne
    {
        return $this->hasOne(User::class)->where('role', UserRole::Admin);
    }
}
