<?php

use App\Models\Screening;
use App\Models\ScreeningResponse;
use App\Models\User;

test('admins can update call log notes', function () {
    $admin = User::factory()->admin()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();
    $callLog = $screeningResponse->callLog;

    $response = $this->actingAs($admin)->patch(route('admin.call-logs.update', $callLog), [
        'notes' => 'Great energy on the call.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('call_logs', [
        'id' => $callLog->id,
        'notes' => 'Great energy on the call.',
    ]);
});

test('agents can not update call log notes', function () {
    $agent = User::factory()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();
    $callLog = $screeningResponse->callLog;

    $response = $this->actingAs($agent)->patch(route('admin.call-logs.update', $callLog), [
        'notes' => 'Great energy on the call.',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('call_logs', [
        'id' => $callLog->id,
        'notes' => null,
    ]);
});

test('guests are redirected to login when updating call log notes', function () {
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();
    $callLog = $screeningResponse->callLog;

    $response = $this->patch(route('admin.call-logs.update', $callLog), [
        'notes' => 'Great energy on the call.',
    ]);

    $response->assertRedirect(route('login'));
});

test('admins can save the candidate scorecard ratings', function () {
    $admin = User::factory()->admin()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();
    $callLog = $screeningResponse->callLog;

    $response = $this->actingAs($admin)->patch(route('admin.call-logs.update', $callLog), [
        'clarity' => 'yes',
        'energy_tone' => 'somewhat',
        'composure_on_pushback' => 'no',
        'overall_gut_check' => 'yes',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('call_logs', [
        'id' => $callLog->id,
        'clarity' => 'yes',
        'energy_tone' => 'somewhat',
        'composure_on_pushback' => 'no',
        'overall_gut_check' => 'yes',
    ]);
});

test('an invalid scorecard rating is rejected', function () {
    $admin = User::factory()->admin()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();
    $callLog = $screeningResponse->callLog;

    $response = $this->actingAs($admin)->patch(route('admin.call-logs.update', $callLog), [
        'clarity' => 'maybe',
    ]);

    $response->assertSessionHasErrors('clarity');
    $this->assertDatabaseHas('call_logs', [
        'id' => $callLog->id,
        'clarity' => null,
    ]);
});
