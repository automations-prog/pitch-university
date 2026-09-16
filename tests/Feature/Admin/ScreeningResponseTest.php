<?php

use App\Models\Screening;
use App\Models\ScreeningResponse;
use App\Models\User;

test('admins can delete a screening response', function () {
    $admin = User::factory()->admin()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();

    $response = $this->actingAs($admin)->delete(route('admin.screening-responses.destroy', $screeningResponse));

    $response->assertRedirect(route('admin.screening.index'));
    $this->assertDatabaseMissing('screening_responses', ['id' => $screeningResponse->id]);
});

test('agents can not delete a screening response', function () {
    $agent = User::factory()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();

    $response = $this->actingAs($agent)->delete(route('admin.screening-responses.destroy', $screeningResponse));

    $response->assertForbidden();
    $this->assertDatabaseHas('screening_responses', ['id' => $screeningResponse->id]);
});

test('admins can bulk delete screening responses', function () {
    $admin = User::factory()->admin()->create();
    $screening = Screening::factory()->create();
    $responses = ScreeningResponse::factory()->for($screening)->count(3)->create();

    $response = $this->actingAs($admin)->delete(route('admin.screening-responses.bulk-destroy'), [
        'ids' => $responses->pluck('id')->toArray(),
    ]);

    $response->assertRedirect(route('admin.screening.index'));
    foreach ($responses as $screeningResponse) {
        $this->assertDatabaseMissing('screening_responses', ['id' => $screeningResponse->id]);
    }
});

test('bulk deleting screening responses requires at least one id', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('admin.screening-responses.bulk-destroy'), [
        'ids' => [],
    ]);

    $response->assertSessionHasErrors('ids');
});

test('agents can not bulk delete screening responses', function () {
    $agent = User::factory()->create();
    $screeningResponse = ScreeningResponse::factory()->for(Screening::factory())->create();

    $response = $this->actingAs($agent)->delete(route('admin.screening-responses.bulk-destroy'), [
        'ids' => [$screeningResponse->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('screening_responses', ['id' => $screeningResponse->id]);
});
