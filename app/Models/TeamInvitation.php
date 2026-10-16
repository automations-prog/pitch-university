<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\TeamInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A shared sign-up link for one team. Anyone who opens it can create an
 * account, which joins the team with the link's role.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property string $token
 * @property UserRole $role
 * @property Carbon|null $expires_at
 * @property int|null $max_uses
 * @property int $uses_count
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'created_by', 'role', 'expires_at', 'max_uses'])]
class TeamInvitation extends Model
{
    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    /**
     * Every state a link can be in, as returned by status().
     *
     * @var list<string>
     */
    public const STATUSES = ['active', 'expired', 'revoked', 'used_up'];

    /**
     * Give every new link an unguessable token.
     */
    protected static function booted(): void
    {
        static::creating(function (TeamInvitation $invitation) {
            $invitation->token ??= Str::random(40);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsedUp(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    /**
     * Whether someone can still sign up with the link.
     */
    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isUsedUp();
    }

    /**
     * The link's state, as shown to admins.
     */
    public function status(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            $this->isUsedUp() => 'used_up',
            default => 'active',
        };
    }

    /**
     * Limit the query to links in the given state, matching status().
     *
     * @param  Builder<TeamInvitation>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, string $status): void
    {
        $notRevoked = fn (Builder $query) => $query->whereNull('revoked_at');
        $notExpired = fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $usedUp = fn (Builder $query) => $query->whereNotNull('max_uses')->whereColumn('uses_count', '>=', 'max_uses');

        match ($status) {
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->where($notRevoked)->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            'used_up' => $query->where($notRevoked)->where($notExpired)->where($usedUp),
            'active' => $query->where($notRevoked)->where($notExpired)->whereNot($usedUp),
            default => null,
        };
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
