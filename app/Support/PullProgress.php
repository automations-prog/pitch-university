<?php

namespace App\Support;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks the progress of a "Pull agents" run so the frontend can poll it,
 * bridging a frontend-generated progress id to the underlying job batch.
 */
class PullProgress
{
    private const TTL_MINUTES = 60;

    public static function start(string $progressId): void
    {
        Cache::put(self::key($progressId), [
            'batchId' => null,
            'finished' => false,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public static function attach(string $progressId, Batch $batch): void
    {
        Cache::put(self::key($progressId), [
            'batchId' => $batch->id,
            'finished' => false,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public static function finish(string $progressId): void
    {
        $state = Cache::get(self::key($progressId), []);

        Cache::put(self::key($progressId), [
            ...$state,
            'finished' => true,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * @return array{finished: bool, totalJobs: int, pendingJobs: int, processedJobs: int, progress: int, failedJobs: int}
     */
    public static function status(string $progressId): array
    {
        $state = Cache::get(self::key($progressId));

        if ($state === null) {
            return [
                'finished' => true,
                'totalJobs' => 0,
                'pendingJobs' => 0,
                'processedJobs' => 0,
                'progress' => 100,
                'failedJobs' => 0,
            ];
        }

        $batch = $state['batchId'] ? Bus::findBatch($state['batchId']) : null;

        if (! $batch) {
            return [
                'finished' => (bool) $state['finished'],
                'totalJobs' => 0,
                'pendingJobs' => 0,
                'processedJobs' => 0,
                'progress' => $state['finished'] ? 100 : 0,
                'failedJobs' => 0,
            ];
        }

        return [
            'finished' => (bool) $state['finished'] && ($batch->finished() || $batch->cancelled()),
            'totalJobs' => $batch->totalJobs,
            'pendingJobs' => $batch->pendingJobs,
            'processedJobs' => $batch->totalJobs - $batch->pendingJobs,
            'progress' => $batch->progress(),
            'failedJobs' => $batch->failedJobs,
        ];
    }

    private static function key(string $progressId): string
    {
        return "pull-agents-progress:{$progressId}";
    }
}
