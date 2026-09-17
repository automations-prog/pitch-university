<?php

use App\Models\Screening;
use App\Models\ScreeningResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the screening index with all responses', function () {
    $admin = User::factory()->admin()->create();
    Screening::factory()->has(ScreeningResponse::factory()->count(2), 'responses')->create();
    Screening::factory()->has(ScreeningResponse::factory()->count(1), 'responses')->create();

    $response = $this->actingAs($admin)->get(route('admin.screening.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/screening/index')
        ->has('responses.data', 3)
        ->has('responses.data.0.public_url')
        ->has('responses.links.0.url')
        ->has('responses.links.0.label')
        ->has('responses.links.0.active')
        ->has('responses.meta.current_page')
        ->missing('responses.meta.links'),
    );
});

test('the screening index can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    Screening::factory()
        ->count(15)
        ->has(ScreeningResponse::factory(), 'responses')
        ->create();

    $response = $this->actingAs($admin)->get(route('admin.screening.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('responses.data', 10)
        ->where('responses.meta.per_page', 10)
        ->where('responses.meta.total', 15)
        ->where('filters.per_page', '10'),
    );

    $response = $this->actingAs($admin)->get(route('admin.screening.index', ['per_page' => 999]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('responses.meta.per_page', 10),
    );
});

test('agents can not view the screening index', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('admin.screening.index'));

    $response->assertForbidden();
});

test('guests are redirected to login for the screening index', function () {
    $response = $this->get(route('admin.screening.index'));

    $response->assertRedirect(route('login'));
});

test('admins can generate a screening link', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.screening.store'));

    $response->assertOk();
    $response->assertJsonStructure(['screening' => ['id', 'token', 'public_url', 'responses_count', 'created_at']]);
    $this->assertDatabaseCount('screenings', 1);
});

test('agents can not generate a screening link', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.screening.store'));

    $response->assertForbidden();
    $this->assertDatabaseCount('screenings', 0);
});

test('admins can view a screening\'s responses', function () {
    $admin = User::factory()->admin()->create();
    $screening = Screening::factory()->has(ScreeningResponse::factory()->count(2), 'responses')->create();

    $response = $this->actingAs($admin)->get(route('admin.screening.show', $screening));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/screening/show')
        ->where('screening.id', $screening->id)
        ->has('responses', 2),
    );
});

test('agents can not view a screening\'s responses', function () {
    $agent = User::factory()->create();
    $screening = Screening::factory()->create();

    $response = $this->actingAs($agent)->get(route('admin.screening.show', $screening));

    $response->assertForbidden();
});

test('admins can view a single screening response', function () {
    $admin = User::factory()->admin()->create();
    $screening = Screening::factory()->has(ScreeningResponse::factory(), 'responses')->create();
    $screeningResponse = $screening->responses()->first();

    $response = $this->actingAs($admin)->get(route('admin.screening-responses.show', $screeningResponse));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/screening-responses/show')
        ->where('response.id', $screeningResponse->id)
        ->where('response.full_name', $screeningResponse->full_name)
        ->where('response.call_log.transcript', null)
        ->where('response.call_log.recording_path', null)
        ->where('response.call_log.notes', null)
        ->where('response.call_log.clarity', null)
        ->where('response.call_log.energy_tone', null)
        ->where('response.call_log.composure_on_pushback', null)
        ->where('response.call_log.overall_gut_check', null),
    );
});

test('agents can not view a single screening response', function () {
    $agent = User::factory()->create();
    $screening = Screening::factory()->has(ScreeningResponse::factory(), 'responses')->create();
    $screeningResponse = $screening->responses()->first();

    $response = $this->actingAs($agent)->get(route('admin.screening-responses.show', $screeningResponse));

    $response->assertForbidden();
});
