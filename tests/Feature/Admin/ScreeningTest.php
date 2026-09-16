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
        ->has('responses', 3)
        ->has('responses.0.public_url'),
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
