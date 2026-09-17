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
        ->where('alreadySubmitted', false),
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

test('a screening link can only be submitted once', function () {
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

    $response->assertStatus(409);
    expect($screening->responses()->count())->toBe(1);
    expect($screening->responses()->first()->full_name)->toBe('Jordan Blake');
});

test('an already-submitted screening link is flagged when viewed again', function () {
    $screening = Screening::factory()->create();

    $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'phone_number' => '555-123-4567',
    ]);

    $response = $this->get(route('screening.show', $screening));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('screening/show')
        ->where('alreadySubmitted', true),
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
