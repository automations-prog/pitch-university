<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A valid sign-up through an invite link.
 *
 * @return array<string, string>
 */
function inviteSignUp(array $overrides = []): array
{
    return [
        'name' => 'New Agent',
        'email' => 'new-agent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'work_location' => 'offshore',
        ...$overrides,
    ];
}

test('signing up with a team link puts the new agent on that team and signs them in', function () {
    $invitation = TeamInvitation::factory()->create();

    $this->post(route('invitations.store', $invitation->token), inviteSignUp())
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'new-agent@example.com')->sole();

    expect($user)
        ->team_id->toBe($invitation->team_id)
        ->work_location->value->toBe('offshore')
        ->hasRole('agent')->toBeTrue()
        ->and($invitation->fresh()->uses_count)->toBe(1);
    $this->assertAuthenticatedAs($user);
});

test('the invite page shows the team and campaign being joined', function () {
    $invitation = TeamInvitation::factory()->create();

    $this->get(route('invitations.show', $invitation->token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/invitation')
            ->where('invitation.team', $invitation->team->name)
            ->where('invitation.campaign', $invitation->team->campaign->name)
            ->where('invitation.status', 'active'));
});

test('expired, revoked and used up links can not be used', function (TeamInvitation $invitation) {
    $this->post(route('invitations.store', $invitation->token), inviteSignUp())
        ->assertSessionHasErrors(['invitation' => 'This invite link can no longer be used.']);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'new-agent@example.com']);
})->with([
    'expired' => fn () => TeamInvitation::factory()->expired()->create(),
    'revoked' => fn () => TeamInvitation::factory()->revoked()->create(),
    'used up' => fn () => TeamInvitation::factory()->create(['max_uses' => 1, 'uses_count' => 1]),
]);

test('an unknown link token is not found', function () {
    $this->get(route('invitations.show', 'not-a-token'))->assertNotFound();
});

test('signing up requires a work location', function () {
    $invitation = TeamInvitation::factory()->create();

    $this->post(route('invitations.store', $invitation->token), inviteSignUp(['work_location' => '']))
        ->assertSessionHasErrors('work_location');
});

test('admins can create links for their own team', function () {
    $manager = User::factory()->admin()->create();
    $team = Team::factory()->managedBy($manager)->create();

    $this->actingAs($manager)
        ->post(route('admin.invitations.store'), ['team_id' => $team->id, 'expires_in_days' => 7, 'max_uses' => 20])
        ->assertRedirect(route('admin.invitations.index'));

    expect($team->invitations()->sole())
        ->created_by->toBe($manager->id)
        ->role->value->toBe('agent')
        ->max_uses->toBe(20)
        ->expires_at->toDateString()->toBe(now()->addDays(7)->toDateString());
});

test('admins can not create links for other teams', function () {
    $manager = User::factory()->admin()->create();
    Team::factory()->managedBy($manager)->create();
    $otherTeam = Team::factory()->create();

    $this->actingAs($manager)
        ->post(route('admin.invitations.store'), ['team_id' => $otherTeam->id])
        ->assertSessionHasErrors('team_id');

    expect(TeamInvitation::count())->toBe(0);
});

test('admins only see and revoke their own team\'s links', function () {
    $manager = User::factory()->admin()->create();
    $ownLink = TeamInvitation::factory()->for(Team::factory()->managedBy($manager))->create();
    $otherLink = TeamInvitation::factory()->create();

    $this->actingAs($manager)
        ->get(route('admin.invitations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invitations.data', 1)
            ->where('invitations.data.0.id', $ownLink->id));

    $this->actingAs($manager)->delete(route('admin.invitations.destroy', $otherLink))->assertForbidden();
    $this->actingAs($manager)->delete(route('admin.invitations.destroy', $ownLink))->assertRedirect(route('admin.invitations.index'));

    expect($ownLink->fresh()->status())->toBe('revoked')
        ->and($otherLink->fresh()->status())->toBe('active');
});

test('agents can not manage invite links', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->get(route('admin.invitations.index'))->assertForbidden();
});

test('invite links can be filtered by status', function (string $status) {
    $superAdmin = User::factory()->superAdmin()->create();
    $links = [
        'active' => TeamInvitation::factory()->create(),
        'expired' => TeamInvitation::factory()->expired()->create(),
        'revoked' => TeamInvitation::factory()->revoked()->create(),
        'used_up' => TeamInvitation::factory()->create(['max_uses' => 2, 'uses_count' => 2]),
    ];

    $this->actingAs($superAdmin)
        ->get(route('admin.invitations.index', ['status' => $status]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invitations.data', 1)
            ->where('invitations.data.0.id', $links[$status]->id)
            ->where('invitations.data.0.status', $status));
})->with(['active', 'expired', 'revoked', 'used_up']);

test('invite links can be filtered by team', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $link = TeamInvitation::factory()->create();
    TeamInvitation::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.invitations.index', ['team' => $link->team_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invitations.data', 1)
            ->where('invitations.data.0.id', $link->id));
});
