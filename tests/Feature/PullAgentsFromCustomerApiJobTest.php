<?php

use App\Jobs\PullAgentsFromCustomerApiJob;
use App\Services\CustomerApiClient;
use App\Support\PullProgress;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.customer_api.domain' => 'https://api.test',
        'services.customer_api.key' => 'secret',
        'services.customer_api.location_id' => '1',
        'services.customer_api.page_size' => 2,
    ]);
});

test('it dispatches one job per roster page, pulling and saving together', function () {
    Bus::fake();

    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $pageIndex = (int) ($query['PageIndex'] ?? -1);

        return match ($pageIndex) {
            0 => Http::response([
                'pagedResults' => ['pagesCount' => 2],
                'students' => [
                    ['id' => '1', 'email' => 'one@example.com'],
                    ['id' => '2', 'email' => 'two@example.com'],
                ],
            ]),
            default => Http::response(['pagedResults' => ['pagesCount' => 2], 'students' => []]),
        };
    });

    (new PullAgentsFromCustomerApiJob)->handle(app(CustomerApiClient::class));

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2);
});

test('it finishes without dispatching a batch when the roster is empty', function () {
    Bus::fake();

    Http::fake([
        'api.test/*' => Http::response([
            'pagedResults' => ['pagesCount' => 1],
            'students' => [],
        ]),
    ]);

    (new PullAgentsFromCustomerApiJob)->handle(app(CustomerApiClient::class));

    Bus::assertNothingBatched();
});

test('it marks the progress as finished when the pull itself fails', function () {
    Http::fake([
        'api.test/*' => Http::response(status: 500),
    ]);

    $progressId = 'test-progress-id';
    PullProgress::start($progressId);

    $job = new PullAgentsFromCustomerApiJob(progressId: $progressId);
    $job->failed(new RuntimeException('boom'));

    expect(PullProgress::status($progressId)['finished'])->toBeTrue();
});
