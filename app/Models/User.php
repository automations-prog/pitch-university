<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\RoleplayVertical;
use App\Enums\UserRole;
use App\Enums\UserSource;
use App\Enums\UserStatus;
use App\Enums\WorkLocation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $excel_id
 * @property array<string, mixed>|null $metadata
 * @property UserSource $source
 * @property UserRole $role
 * @property UserStatus $status
 * @property WorkLocation|null $work_location
 * @property int|null $team_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'status', 'work_location', 'team_id', 'excel_id', 'metadata', 'source'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Impersonate, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'agent',
    ];

    /**
     * The `role` column is the user's one role; keep the Spatie role that
     * grants its permissions in step with it.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->wasRecentlyCreated || $user->wasChanged('role')) {
                $user->syncRoles([$user->role->value]);
            }
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'work_location' => WorkLocation::class,
            'metadata' => 'array',
            'source' => UserSource::class,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Whether this user is allowed to impersonate other users.
     */
    public function canImpersonate(): bool
    {
        return $this->can(Permission::ImpersonateUsers->value);
    }

    /**
     * Whether this user is allowed to be impersonated.
     */
    public function canBeImpersonated(): bool
    {
        return ! $this->isSuperAdmin() && $this->isActive();
    }

    /**
     * Whether this user may practice the given roleplay vertical: they need
     * its permission, and agents also need a track assigned that opens it.
     */
    public function canPracticeRoleplay(RoleplayVertical $vertical): bool
    {
        if (! $this->can(Permission::forRoleplay($vertical))) {
            return false;
        }

        return $this->role !== UserRole::Agent
            || $this->courseTracks()->where('vertical', $vertical)->exists();
    }

    /**
     * The roles this user may give to other users.
     *
     * @return list<UserRole>
     */
    public function assignableRoles(): array
    {
        return $this->role->assignableRoles();
    }

    /**
     * Limit the query to the users the viewer may see: everyone for super
     * admins, themself plus their team's members for admins, otherwise
     * only themself.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->isSuperAdmin()) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereKey($viewer->id)
            ->when($viewer->managedTeamId(), fn (Builder $query, int $teamId) => $query->orWhere('team_id', $teamId)));
    }

    /**
     * Whether the given user is the viewer or on the team the viewer runs.
     */
    public function canSee(User $user): bool
    {
        return $this->isSuperAdmin()
            || $this->is($user)
            || ($this->managedTeamId() !== null && $user->team_id === $this->managedTeamId());
    }

    /**
     * Whether this user may change the given user: super admins anyone
     * else, admins the members of their team whose role they could have
     * assigned.
     */
    public function canManage(User $user): bool
    {
        if ($this->is($user)) {
            return false;
        }

        return $this->isSuperAdmin()
            || ($this->canSee($user) && in_array($user->role, $this->assignableRoles(), true));
    }

    /**
     * The team this user runs: an admin's own team. Other roles run none.
     */
    public function managedTeamId(): ?int
    {
        return $this->role === UserRole::Admin ? $this->team_id : null;
    }

    /**
     * The teams this user may put users on: every team for super admins,
     * otherwise the team they run.
     *
     * @return Builder<Team>
     */
    public function assignableTeams(): Builder
    {
        return $this->isSuperAdmin()
            ? Team::query()
            : Team::query()->whereKey($this->managedTeamId() ?? []);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function licenses(): BelongsToMany
    {
        return $this->belongsToMany(License::class)->withTimestamps();
    }

    /**
     * The training tracks this user has been given access to.
     *
     * @return BelongsToMany<CourseTrack, $this>
     */
    public function courseTracks(): BelongsToMany
    {
        return $this->belongsToMany(CourseTrack::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<CourseLesson, $this>
     */
    public function completedCourseLessons(): BelongsToMany
    {
        return $this->belongsToMany(CourseLesson::class)->withTimestamps();
    }

    /**
     * @return HasMany<CourseQuizAttempt, $this>
     */
    public function courseQuizAttempts(): HasMany
    {
        return $this->hasMany(CourseQuizAttempt::class);
    }

    /**
     * @return HasMany<CourseExamAttempt, $this>
     */
    public function courseExamAttempts(): HasMany
    {
        return $this->hasMany(CourseExamAttempt::class);
    }

    /**
     * @return HasMany<RoleplaySession, $this>
     */
    public function roleplaySessions(): HasMany
    {
        return $this->hasMany(RoleplaySession::class);
    }
}
