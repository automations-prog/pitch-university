<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Enums\UserSource;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\CustomerApiClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls a single page of the roster from the Customer API and saves it,
 * so pulling and saving happen together one page at a time instead of
 * fetching the whole roster up front and saving it in a separate pass.
 */
class PullAndSyncAgentsPageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @param  array<int, array<string, mixed>>|null  $preloadedStudents  Students already fetched for this page, if any, to avoid re-fetching page 0.
     */
    public function __construct(
        private readonly int $pageIndex,
        private readonly ?array $preloadedStudents = null,
    ) {}

    public function handle(CustomerApiClient $client): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $students = $this->preloadedStudents ?? ($client->fetchTrainingProgressPage($this->pageIndex)['students'] ?? []);

        $emails = collect($students)
            ->map(fn (array $student) => $student['email'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $existingEmails = User::whereIn('email', $emails)->pluck('email')->flip();

        foreach ($students as $student) {
            $email = $student['email'] ?? null;

            if (! $email || $existingEmails->has($email)) {
                continue;
            }

            try {
                $user = $this->createAgent($student, $email);
                $existingEmails->put($email, $user->id);
            } catch (Throwable $e) {
                Log::warning('Skipped creating a Customer API agent account.', [
                    'excel_id' => $student['id'] ?? null,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function createAgent(array $student, string $email): User
    {
        $name = trim(($student['firstName'] ?? '').' '.($student['lastName'] ?? ''));

        return User::create([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'password' => (string) str()->random(32),
            'role' => UserRole::Agent,
            'status' => UserStatus::Active,
            'excel_id' => $student['id'] ?? null,
            'metadata' => collect($student)->except(['id', 'email'])->all(),
            'source' => UserSource::CustomerApi,
        ]);
    }
}
