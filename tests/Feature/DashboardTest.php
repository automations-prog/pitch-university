<?php

use App\Models\License;
use App\Models\User;
use App\Models\VerticalTraining;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('agents see their own progress instead of admin stats', function () {
    $agent = User::factory()->create();
    VerticalTraining::factory()->count(2)->create();
    VerticalTraining::factory()->inactive()->create();

    $response = $this->actingAs($agent)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('isAdmin', false)
        ->has('progress.trainings_completed')
        ->where('progress.total_trainings', 2)
        ->has('trainingScores', 2)
        ->missing('stats')
        ->missing('agents')
        ->missing('charts'),
    );
});

test('an agent\'s training scores are per-training, deterministic, and exclude inactive trainings', function () {
    $agent = User::factory()->create();
    $training = VerticalTraining::factory()->create(['name' => 'Cold Calling']);
    VerticalTraining::factory()->inactive()->create();

    $seed = crc32($agent->id.'-'.$training->id);
    $completed = $seed % 2;
    $expectedScore = $completed > 0 ? 55 + ($seed % 46) : null;

    $response = $this->actingAs($agent)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('trainingScores', 1)
        ->where('trainingScores.0.id', $training->id)
        ->where('trainingScores.0.name', 'Cold Calling')
        ->where('trainingScores.0.trainings_completed', $completed)
        ->where('trainingScores.0.average_score', $expectedScore),
    );

    // Same agent + training seed should stay stable across requests.
    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('trainingScores.0.average_score', $expectedScore),
    );
});

test('admins see progress stats and the agent table', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->inactive()->create();
    VerticalTraining::factory()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('isAdmin', true)
        ->has('stats.total_agents')
        ->has('stats.active_agents')
        ->has('stats.inactive_agents')
        ->has('stats.total_trainings')
        ->has('agents.data', 3)
        ->has('agents.links')
        ->has('agents.meta.current_page')
        ->missing('agents.meta.links')
        ->has('licenses')
        ->has('trainings')
        ->has('charts.status_split', 2)
        ->has('charts.completion', 3)
        ->has('charts.score_bands', 4)
        ->has('charts.per_license'),
    );
});

test('the admin dashboard agent table can be filtered by status', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->inactive()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['status' => 'inactive']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 3)
        ->where('agents.data.0.status', 'inactive'),
    );
});

test('the admin dashboard agent table can be filtered by license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $licensedAgent = User::factory()->create();
    $licensedAgent->licenses()->attach($license);
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['license' => $license->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 1)
        ->where('agents.data.0.id', $licensedAgent->id),
    );
});

test('the admin dashboard progress narrows to a single vertical training when filtered', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();
    $training = VerticalTraining::factory()->create();
    VerticalTraining::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['vertical_training' => $training->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 3)
        ->where('agents.data.0.total_trainings', 1),
    );
});

test('the admin dashboard agent table can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('agents.meta.total', 24)
        ->where('agents.meta.last_page', 3)
        ->where('agents.meta.per_page', 10)
        ->where('filters.per_page', '10'),
    );

    $secondPage = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 10, 'page' => 2]));
    $secondPage->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('agents.meta.current_page', 2),
    );
});

test('the dashboard charts summarize every filtered agent, not just the current page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('charts.status_split.0.value', 24),
    );
});

test('an invalid per_page value on the dashboard falls back to the default', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 999]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('agents.meta.per_page', 10)
        ->where('filters.per_page', '10'),
    );
});

test('inactive licenses and trainings are excluded from the dashboard filter options', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->inactive()->create();
    VerticalTraining::factory()->inactive()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('licenses', 0)
        ->has('trainings', 0),
    );
});

test('the per-license chart counts agents assigned to each active license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['name' => 'Sales Fundamentals']);
    License::factory()->inactive()->create();
    $agent = User::factory()->create();
    $agent->licenses()->attach($license);
    User::factory()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('charts.per_license', 1)
        ->where('charts.per_license.0.name', 'Sales Fundamentals')
        ->where('charts.per_license.0.value', 1),
    );
});

test('the dashboard charts reflect the filtered agent set', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->inactive()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['status' => 'inactive']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('charts.status_split.0.value', 0)
        ->where('charts.status_split.1.value', 3),
    );
});
