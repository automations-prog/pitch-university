<?php

use App\Models\Screening;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can view the public screening form without authentication', function () {
    $screening = Screening::factory()->create();

    $response = $this->get(route('screening.show', $screening));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('screening/show')
        ->where('token', $screening->token)
        ->where('responseToken', null),
    );
});

test('an invalid screening link is not found', function () {
    $response = $this->get(route('screening.show', 'not-a-real-token'));

    $response->assertNotFound();
});

test('guests can submit a response to the screening form', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);

    $response->assertRedirect(route('screening.show', $screening));
    $this->assertDatabaseHas('screening_responses', [
        'screening_id' => $screening->id,
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);
});

test('submitting a response automatically creates a call log', function () {
    $screening = Screening::factory()->create();

    $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);

    $response = $screening->responses()->first();

    $this->assertDatabaseHas('call_logs', [
        'screening_response_id' => $response->id,
        'called_at' => null,
        'transcript' => null,
        'recording_path' => null,
        'notes' => null,
    ]);
});

test('a screening link can be submitted by multiple candidates', function () {
    $screening = Screening::factory()->create();

    $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'phone_number' => '555-987-6543',
    ]);

    $response->assertRedirect(route('screening.show', $screening));
    expect($screening->responses()->count())->toBe(2);
    expect($screening->responses()->pluck('full_name'))
        ->toContain('Jordan Blake')
        ->toContain('Sam Rivera');
});

test('each screening response gets its own token', function () {
    $screening = Screening::factory()->create();

    $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);

    $response = $this->get(route('screening.show', $screening));

    $responseToken = $screening->responses()->first()->token;

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('screening/show')
        ->where('responseToken', $responseToken),
    );
});

test('the screening response requires all fields', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), []);

    $response->assertSessionHasErrors(['full_name', 'email', 'phone_number']);
});

test('the screening response email must be a valid email address', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'not-an-email',
        'phone_number' => '555-123-4567',
    ]);

    $response->assertSessionHasErrors('email');
});

test('the screening response full name must contain only letters, spaces, hyphens and apostrophes', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan123',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);

    $response->assertSessionHasErrors('full_name');
});

test('the screening response phone number must contain only digits and phone symbols', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => 'call-me-maybe',
    ]);

    $response->assertSessionHasErrors('phone_number');
});
