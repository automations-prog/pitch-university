<?php

use App\Enums\UserSource;
use App\Jobs\PullAndSyncAgentsPageJob;
use App\Models\User;
use App\Services\CustomerApiClient;
use Illuminate\Support\Facades\Http;

test('it creates a user for each new student in the page', function () {
    $job = new PullAndSyncAgentsPageJob(0, [
        ['id' => 'xcel-1', 'email' => 'new.student@example.com', 'firstName' => 'New', 'lastName' => 'Student'],
    ]);

    $job->handle(app(CustomerApiClient::class));

    $user = User::where('email', 'new.student@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Student')
        ->and($user->excel_id)->toBe('xcel-1')
        ->and($user->source)->toBe(UserSource::CustomerApi)
        ->and($user->metadata)->toMatchArray(['firstName' => 'New', 'lastName' => 'Student']);
});

test('it skips students whose email already has a user account', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $job = new PullAndSyncAgentsPageJob(0, [
        ['id' => 'xcel-2', 'email' => 'existing@example.com', 'firstName' => 'Existing'],
    ]);

    $job->handle(app(CustomerApiClient::class));

    expect(User::where('email', 'existing@example.com')->count())->toBe(1)
        ->and(User::where('email', 'existing@example.com')->first()->excel_id)->toBeNull();
});

test('it skips students with no email', function () {
    $job = new PullAndSyncAgentsPageJob(0, [
        ['id' => 'xcel-3', 'firstName' => 'No', 'lastName' => 'Email'],
    ]);

    $job->handle(app(CustomerApiClient::class));

    expect(User::count())->toBe(0);
});

test('it deduplicates students sharing the same email within the page', function () {
    $job = new PullAndSyncAgentsPageJob(0, [
        ['id' => 'xcel-4', 'email' => 'duplicate@example.com', 'firstName' => 'First'],
        ['id' => 'xcel-5', 'email' => 'duplicate@example.com', 'firstName' => 'Second'],
    ]);

    $job->handle(app(CustomerApiClient::class));

    expect(User::where('email', 'duplicate@example.com')->count())->toBe(1);
});

test('it fetches the page itself when no students are preloaded', function () {
    config([
        'services.customer_api.domain' => 'https://api.test',
        'services.customer_api.key' => 'secret',
        'services.customer_api.location_id' => '1',
    ]);

    Http::fake([
        'api.test/*' => Http::response([
            'pagedResults' => ['pagesCount' => 2],
            'students' => [
                ['id' => 'xcel-6', 'email' => 'fetched@example.com', 'firstName' => 'Fetched'],
            ],
        ]),
    ]);

    (new PullAndSyncAgentsPageJob(1))->handle(app(CustomerApiClient::class));

    expect(User::where('email', 'fetched@example.com')->exists())->toBeTrue();
});
