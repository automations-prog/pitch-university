<?php

namespace App\Jobs;

use App\Enums\UserSource;
use App\Models\User;
use App\Services\CustomerApiClient;
use App\Support\PullProgress;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class PullAgentsFromCustomerApiJob implements ShouldQueue
{
    use Queueable;

    /**
     * Discovering the roster's page count is a single, cheap request; give
     * it modest headroom without holding the queue up if the API stalls.
     */
    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        private readonly ?User $triggeredBy = null,
        private readonly ?string $progressId = null,
    ) {}

    public function handle(CustomerApiClient $client): void
    {
        $triggeredBy = $this->triggeredBy;
        $progressId = $this->progressId;

        $firstPage = $client->fetchTrainingProgressPage(0);
        $pagesCount = (int) ($firstPage['pagedResults']['pagesCount'] ?? 0);
        $students = $firstPage['students'] ?? [];

        if ($pagesCount === 0 || $students === []) {
            if ($progressId) {
                PullProgress::finish($progressId);
            }

            self::notifyResult($triggeredBy, 0);

            return;
        }

        $accountsBefore = User::where('source', UserSource::CustomerApi)->count();

        // Each page is pulled from the API and saved by its own job, one
        // page at a time, instead of loading the whole roster up front.
        $jobs = [new PullAndSyncAgentsPageJob(0, $students)];

        for ($pageIndex = 1; $pageIndex < $pagesCount; $pageIndex++) {
            $jobs[] = new PullAndSyncAgentsPageJob($pageIndex);
        }

        $batch = Bus::batch($jobs)
            ->name('agent-roster-sync')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($triggeredBy, $progressId, $accountsBefore) {
                if ($progressId) {
                    PullProgress::finish($progressId);
                }

                $created = User::where('source', UserSource::CustomerApi)->count() - $accountsBefore;

                self::notifyResult($triggeredBy, $created);
            })
            ->dispatch();

        if ($progressId) {
            PullProgress::attach($progressId, $batch);
        }
    }

    /**
     * Make sure the frontend's progress poll always resolves, even when the
     * roster fetch itself fails before a batch is ever dispatched.
     */
    public function failed(?Throwable $exception): void
    {
        if ($this->progressId) {
            PullProgress::finish($this->progressId);
        }

        Log::error('Customer API agent roster pull failed.', [
            'triggered_by' => $this->triggeredBy?->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    private static function notifyResult(?User $triggeredBy, int $created): void
    {
        Log::info('Customer API agent roster sync finished.', [
            'triggered_by' => $triggeredBy?->id,
            'accounts_created' => $created,
        ]);
    }
}
