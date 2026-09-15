<?php

use App\Models\License;
use App\Models\User;
use App\Models\VerticalTraining;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the vertical training index', function () {
    $admin = User::factory()->admin()->create();
    VerticalTraining::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/vertical-training/index')
        ->has('trainings.data', 3)
        ->has('trainings.links.0.url')
        ->has('trainings.meta.current_page')
        ->missing('trainings.meta.links'),
    );
});

test('the vertical training index can be searched and filtered by status', function () {
    $admin = User::factory()->admin()->create();
    VerticalTraining::factory()->create(['name' => 'Healthcare Vertical']);
    VerticalTraining::factory()->inactive()->create(['name' => 'Retail Vertical']);

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.index', ['search' => 'Healthcare']));
    $response->assertInertia(fn (Assert $page) => $page->has('trainings.data', 1));

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.index', ['status' => 'inactive']));
    $response->assertInertia(fn (Assert $page) => $page->has('trainings.data', 1));
});

test('the vertical training index can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    VerticalTraining::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/vertical-training/index')
        ->has('trainings.data', 10)
        ->where('trainings.meta.per_page', 10)
        ->where('trainings.meta.total', 24)
        ->where('filters.per_page', '10')
        ->where('perPageOptions', [10, 25, 50, 100]),
    );

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.index', ['per_page' => 999]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('trainings.meta.per_page', 10),
    );
});

test('agents can not view the vertical training index', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('admin.vertical-training.index'));

    $response->assertForbidden();
});

test('guests are redirected to login for vertical training', function () {
    $response = $this->get(route('admin.vertical-training.index'));

    $response->assertRedirect(route('login'));
});

test('admins can create a vertical training program', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.vertical-training.store'), [
        'name' => 'Healthcare Vertical',
        'status' => 'active',
        'license_id' => $license->id,
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect who has never been contacted.',
        'script_body' => "Agent: Hi, this is...\nProspect: ...",
    ]);

    $response->assertRedirect(route('admin.vertical-training.index'));
    $this->assertDatabaseHas('vertical_trainings', [
        'name' => 'Healthcare Vertical',
        'status' => 'active',
        'license_id' => $license->id,
    ]);
});

test('admins can create a training with a roleplay script', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.vertical-training.store'), [
        'name' => 'Healthcare Vertical',
        'status' => 'active',
        'license_id' => $license->id,
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect who has never been contacted.',
        'script_body' => "Agent: Hi, this is...\nProspect: ...",
    ]);

    $response->assertRedirect(route('admin.vertical-training.index'));
    $this->assertDatabaseHas('vertical_trainings', [
        'name' => 'Healthcare Vertical',
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect who has never been contacted.',
    ]);
});

test('the roleplay script fields are required when creating a training', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.vertical-training.store'), [
        'name' => 'No Script Vertical',
        'status' => 'active',
        'license_id' => $license->id,
    ]);

    $response->assertSessionHasErrors(['script_title', 'script_scenario', 'script_body']);
    $this->assertDatabaseMissing('vertical_trainings', [
        'name' => 'No Script Vertical',
    ]);
});

test('a vertical training requires a license', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.vertical-training.store'), [
        'name' => 'No License Vertical',
        'status' => 'active',
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect who has never been contacted.',
        'script_body' => "Agent: Hi, this is...\nProspect: ...",
    ]);

    $response->assertSessionHasErrors('license_id');
    $this->assertDatabaseMissing('vertical_trainings', [
        'name' => 'No License Vertical',
    ]);
});

test('a vertical training must reference an active license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->inactive()->create();

    $response = $this->actingAs($admin)->post(route('admin.vertical-training.store'), [
        'name' => 'Inactive License Vertical',
        'status' => 'active',
        'license_id' => $license->id,
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect who has never been contacted.',
        'script_body' => "Agent: Hi, this is...\nProspect: ...",
    ]);

    $response->assertSessionHasErrors('license_id');
    $this->assertDatabaseMissing('vertical_trainings', [
        'name' => 'Inactive License Vertical',
    ]);
});

test('admins can update a training\'s roleplay script', function () {
    $admin = User::factory()->admin()->create();
    $training = VerticalTraining::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.vertical-training.update', $training), [
        'name' => $training->name,
        'status' => 'active',
        'license_id' => $license->id,
        'script_title' => 'Objection handling',
        'script_scenario' => 'Prospect pushes back on price.',
        'script_body' => 'Agent: I understand price is a concern...',
    ]);

    $response->assertRedirect(route('admin.vertical-training.index'));
    $this->assertDatabaseHas('vertical_trainings', [
        'id' => $training->id,
        'script_title' => 'Objection handling',
    ]);
});

test('the edit page exposes the roleplay script fields', function () {
    $admin = User::factory()->admin()->create();
    $training = VerticalTraining::factory()->create([
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect.',
        'script_body' => 'Agent: Hi...',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.edit', $training));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('training.script_title', 'Cold call — initial outreach')
        ->where('training.script_scenario', 'Agent calls a prospect.')
        ->where('training.has_script', true),
    );
});

test('the index reflects whether a training has a script', function () {
    $admin = User::factory()->admin()->create();
    VerticalTraining::factory()->create([
        'name' => 'Zzyzx Scripted Vertical',
        'script_title' => 'Has a script',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.index', ['search' => 'Zzyzx']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('trainings.data.0.has_script', true),
    );
});

test('vertical training names must be unique', function () {
    $admin = User::factory()->admin()->create();
    VerticalTraining::factory()->create(['name' => 'Healthcare Vertical']);
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.vertical-training.store'), [
        'name' => 'Healthcare Vertical',
        'status' => 'active',
        'license_id' => $license->id,
        'script_title' => 'Cold call — initial outreach',
        'script_scenario' => 'Agent calls a prospect who has never been contacted.',
        'script_body' => "Agent: Hi, this is...\nProspect: ...",
    ]);

    $response->assertSessionHasErrors('name');
});

test('agents can not create a vertical training program', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.vertical-training.store'), [
        'name' => 'Blocked Vertical',
        'status' => 'active',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('vertical_trainings', ['name' => 'Blocked Vertical']);
});

test('admins can update a vertical training program', function () {
    $admin = User::factory()->admin()->create();
    $training = VerticalTraining::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.vertical-training.update', $training), [
        'name' => $training->name,
        'status' => 'inactive',
        'license_id' => $license->id,
    ]);

    $response->assertRedirect(route('admin.vertical-training.index'));
    $this->assertDatabaseHas('vertical_trainings', [
        'id' => $training->id,
        'status' => 'inactive',
    ]);
});

test('admins can delete a vertical training program', function () {
    $admin = User::factory()->admin()->create();
    $training = VerticalTraining::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.vertical-training.destroy', $training));

    $response->assertRedirect(route('admin.vertical-training.index'));
    $this->assertDatabaseMissing('vertical_trainings', ['id' => $training->id]);
});

test('agents can not delete a vertical training program', function () {
    $agent = User::factory()->create();
    $training = VerticalTraining::factory()->create();

    $response = $this->actingAs($agent)->delete(route('admin.vertical-training.destroy', $training));

    $response->assertForbidden();
    $this->assertDatabaseHas('vertical_trainings', ['id' => $training->id]);
});

test('admins can bulk delete vertical training programs', function () {
    $admin = User::factory()->admin()->create();
    $trainings = VerticalTraining::factory()->count(3)->create();

    $response = $this->actingAs($admin)->delete(route('admin.vertical-training.bulk-destroy'), [
        'ids' => $trainings->pluck('id')->toArray(),
    ]);

    $response->assertRedirect(route('admin.vertical-training.index'));
    foreach ($trainings as $training) {
        $this->assertDatabaseMissing('vertical_trainings', ['id' => $training->id]);
    }
});

test('bulk deleting vertical training programs requires at least one id', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('admin.vertical-training.bulk-destroy'), [
        'ids' => [],
    ]);

    $response->assertSessionHasErrors('ids');
});

test('agents can not bulk delete vertical training programs', function () {
    $agent = User::factory()->create();
    $training = VerticalTraining::factory()->create();

    $response = $this->actingAs($agent)->delete(route('admin.vertical-training.bulk-destroy'), [
        'ids' => [$training->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('vertical_trainings', ['id' => $training->id]);
});

test('the edit page exposes the training as a flat, unwrapped object', function () {
    $admin = User::factory()->admin()->create();
    $training = VerticalTraining::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.vertical-training.edit', $training));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/vertical-training/edit')
        ->where('training.id', $training->id)
        ->missing('training.data'),
    );
});
