<?php

use App\Models\Screening;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can view the public screening form without authentication', function () {
    $screening = Screening::factory()->create();

    $response = $this->get(route('screening.show', $screening));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('screening/show')
        ->where('token', $screening->token),
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
        'birthday' => '1995-05-10',
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

test('the same screening link accepts multiple responses', function () {
    $screening = Screening::factory()->create();

    $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'birthday' => '1995-05-10',
        'phone_number' => '555-123-4567',
    ]);

    $this->post(route('screening.store', $screening), [
        'full_name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'birthday' => '1998-02-20',
        'phone_number' => '555-987-6543',
    ]);

    expect($screening->responses()->count())->toBe(2);
});

test('the screening response requires all fields', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), []);

    $response->assertSessionHasErrors(['full_name', 'email', 'birthday', 'phone_number']);
});

test('the screening response birthday must be in the past', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'birthday' => now()->addDay()->toDateString(),
        'phone_number' => '555-123-4567',
    ]);

    $response->assertSessionHasErrors('birthday');
});

test('the screening response email must be a valid email address', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'not-an-email',
        'birthday' => '1995-05-10',
        'phone_number' => '555-123-4567',
    ]);

    $response->assertSessionHasErrors('email');
});

test('the screening response full name must contain only letters, spaces, hyphens and apostrophes', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan123',
        'email' => 'jordan@example.com',
        'birthday' => '1995-05-10',
        'phone_number' => '555-123-4567',
    ]);

    $response->assertSessionHasErrors('full_name');
});

test('the screening response phone number must contain only digits and phone symbols', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.store', $screening), [
        'full_name' => 'Jordan Blake',
        'email' => 'jordan@example.com',
        'birthday' => '1995-05-10',
        'phone_number' => 'call-me-maybe',
    ]);

    $response->assertSessionHasErrors('phone_number');
});
