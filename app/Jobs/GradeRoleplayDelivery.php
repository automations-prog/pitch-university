<?php

namespace App\Jobs;

use App\Models\RoleplaySession;
use App\Services\RoleplayDeliveryGrader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Scores a finished roleplay call against the `guide.md` delivery criteria
 * in the background, since the AI review takes a few seconds. The wrap-up
 * screen polls until `delivery_status` leaves `pending`.
 */
class GradeRoleplayDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 10;

    /**
     * Must stay under the queue's `retry_after` (90s), or a slow review is
     * handed to a second worker while the first is still running. The
     * OpenAI request itself times out at 60s.
     */
    public int $timeout = 75;

    /**
     * Its own queue, so a big admin agent sync can't hold up trainees'
     * scores (and the other way round).
     */
    public function __construct(public readonly RoleplaySession $roleplaySession)
    {
        $this->onQueue('roleplay');
    }

    /**
     * Execute the job.
     */
    public function handle(RoleplayDeliveryGrader $grader): void
    {
        $this->roleplaySession->update([
            'delivery' => $grader->grade($this->roleplaySession),
            'delivery_status' => 'done',
        ]);
    }

    /**
     * Keep the measured criteria even when the AI review fails.
     */
    public function failed(?Throwable $exception): void
    {
        $this->roleplaySession->update([
            'delivery' => app(RoleplayDeliveryGrader::class)->measured($this->roleplaySession),
            'delivery_status' => 'failed',
        ]);
    }
}
