<?php

namespace App\Jobs;

use App\Models\RoleplaySession;
use App\Services\RoleplaySceneGrader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Judges a finished training scene's AI-judged checks in the background and
 * settles whether it passed. The wrap-up screen polls until `passed` is set.
 */
class JudgeRoleplayScene implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 10;

    /**
     * Must stay under the queue's `retry_after` (90s). The OpenAI request
     * itself times out at 60s.
     */
    public int $timeout = 75;

    public function __construct(public readonly RoleplaySession $roleplaySession) {}

    /**
     * Execute the job.
     */
    public function handle(RoleplaySceneGrader $grader): void
    {
        $this->settle($grader->judge($this->roleplaySession));
    }

    /**
     * Fail the scene when the AI review can't run, so the trainee isn't
     * left waiting. They can try the scene again.
     */
    public function failed(?Throwable $exception): void
    {
        $this->settle([[
            'key' => 'ai_review',
            'label' => "The AI review didn't go through. Try the scene again.",
            'passed' => false,
        ]]);
    }

    /**
     * @param  list<array{key: string, label: string, passed: bool, feedback?: string}>  $judgedChecks
     */
    private function settle(array $judgedChecks): void
    {
        $session = $this->roleplaySession->fresh();
        $checks = [...$session->score['checks'] ?? [], ...$judgedChecks];

        $session->update([
            'score' => [...$session->score ?? [], 'checks' => $checks],
            'passed' => RoleplaySceneGrader::allPassed($checks),
        ]);
    }
}
