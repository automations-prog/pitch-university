# Implementing agent + training-progress pulls in another project

This is a portable writeup of the "pull agents from an external roster" and
"pull each agent's training progress" pattern used in this project
(`pps-lms`), so it can be re-implemented in another Laravel app. It's a
recipe, not a copy-paste package — file/model names will differ per project.

## Overview

Two independent sync pipelines, same shape:

1. **Agent pull** — page through an external roster API, create a local
   `User` (or equivalent) for every person not already registered.
2. **Training pull** — for every already-linked user, fetch their training
   records from the same (or another) API and upsert them locally.

Both run two ways: manually (an admin clicks a button) and on a schedule
(nightly/hourly cron), sharing the same job code either way.

## Prerequisites in the target project

- A `users` table (or similar) with a unique external-id column
  (`excel_id` here) to correlate local accounts with the external system's
  records, and a `metadata` JSON column to stash the raw payload.
- A way to distinguish "pulled in automatically" accounts from manually
  created ones (`source` enum/string column).
- Laravel queues configured (`ShouldQueue`) — these pulls page through
  potentially large rosters and must not run inline on a web request.

## 1. The API client

Wrap the external HTTP API in a single service class. Keep paging logic
here, not in the job.

```php
// app/Services/CustomerApiClient.php
class CustomerApiClient
{
    private const PAGE_SIZE = 50;

    public function fetchTrainingProgressPage(int $pageIndex): array { /* one HTTP call */ }

    public function fetchUserTrainingProgress(string $userId): array { /* one HTTP call */ }

    /** Lazily yield every record across every page. */
    public function eachStudent(): Generator
    {
        $pageIndex = 0;
        do {
            $page = $this->fetchTrainingProgressPage($pageIndex);
            foreach ($page['students'] ?? [] as $student) {
                yield $student;
            }
            $pageNumber = (int) ($page['pagedResults']['pageNumber'] ?? 1);
            $pagesCount = (int) ($page['pagedResults']['pagesCount'] ?? 1);
            $pageIndex++;
        } while ($pageNumber < $pagesCount);
    }
}
```

Key points:

- Config-driven auth (domain/API key/location id) pulled from `config('services.customer_api.*')`, not hardcoded.
- Throw a clear `RuntimeException` if config is missing, instead of a cryptic HTTP failure.
- `Generator` (lazy `yield`) keeps memory flat regardless of roster size.

## 2. Pulling agents (roster → local users)

Split into two jobs so a large roster can't blow a single queue worker's
timeout: one **orchestrator** that pages the whole roster and fans work out,
one **batch worker** that does the actual writes in small chunks.

### Orchestrator job

```php
// app/Jobs/PullAgentsFromCustomerApiJob.php
class PullAgentsFromCustomerApiJob implements ShouldQueue
{
    private const BATCH_SIZE = 50;
    public int $timeout = 300;

    public function __construct(
        private readonly ?User $triggeredBy = null,
        private readonly ?string $progressId = null,
    ) {}

    public function handle(CustomerApiClient $client): void
    {
        $students = iterator_to_array($client->eachStudent(), false);
        $created = $this->countPendingAccounts($students); // pre-count for the notification

        $jobs = collect($students)
            ->chunk(self::BATCH_SIZE)
            ->map(fn ($chunk) => new SyncAgentsFromCustomerApiBatchJob($chunk->values()->all()))
            ->all();

        Bus::batch($jobs)
            ->name('agent-roster-sync')
            ->allowFailures()
            ->finally(fn (Batch $batch) => self::notifyResult($this->triggeredBy, $created))
            ->dispatch();
    }
}
```

### Batch worker job

```php
// app/Jobs/SyncAgentsFromCustomerApiBatchJob.php
class SyncAgentsFromCustomerApiBatchJob implements ShouldQueue
{
    use Batchable, ...;

    public function __construct(private readonly array $students) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) return;

        $existingEmails = User::whereIn('email', $this->emails())->pluck('email')->flip();

        foreach ($this->students as $student) {
            $email = $student['email'] ?? null;
            if (! $email || $existingEmails->has($email)) continue;

            try {
                $user = $this->createAgent($student, $email);
                $existingEmails->put($email, $user->id); // dedupe within this chunk too
            } catch (Throwable $e) {
                Log::warning('Skipped creating agent account.', ['error' => $e->getMessage()]);
            }
        }
    }

    private function createAgent(array $student, string $email): User
    {
        $user = User::create([
            'name' => trim(($student['firstName'] ?? '').' '.($student['lastName'] ?? '')) ?: $email,
            'email' => $email,
            'password' => 'password',       // default; forces a change below
            'is_active' => true,
            'must_change_password' => true,
            'excel_id' => $student['id'] ?? null,
            'metadata' => collect($student)->except(['id', 'email'])->all(),
            'source' => User::SOURCE_CUSTOMER_API,
        ]);

        $user->syncRoles(['agent']); // Spatie permission role, if you use one

        return $user;
    }
}
```

Design decisions worth keeping when you port this:

- **One student's failure doesn't fail the batch** — wrap each create in try/catch and log+skip.
- **Dedupe by email up front and within the loop** — two students in the same chunk sharing an email must not double-create.
- **Batch jobs, not one giant job** — `Bus::batch()` with `allowFailures()` and a `finally()` callback for the completion notification/log.

## 3. Pulling training progress (per-user sync)

Unlike the agent pull, this doesn't need batching/chunking — it's one HTTP
call per already-known user, so a single job looping over agents is fine.
Factor the actual sync-one-user logic into an **Action** class so it can be
reused both by the scheduled bulk job and by an on-demand "resync this one
agent" controller action.

```php
// app/Actions/SyncTrainingProgressForUser.php
class SyncTrainingProgressForUser
{
    public function handle(User $user, CustomerApiClient $client): void
    {
        if (! $user->excel_id) return; // nothing to correlate against

        $response = $client->fetchUserTrainingProgress($user->excel_id);
        $programs = $response['TrainingPrograms'] ?? $response['trainingPrograms'] ?? [];

        foreach ($programs as $program) {
            TrainingProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'enrollment_id' => (string) ($program['EnrollmentId'] ?? $program['ID'] ?? ''),
                ],
                [
                    'metadata' => $program,
                    'synced_at' => now(),
                ],
            );
        }
    }
}
```

```php
// app/Jobs/SyncAllAgentsTrainingProgressJob.php
class SyncAllAgentsTrainingProgressJob implements ShouldQueue
{
    public function handle(CustomerApiClient $client, SyncTrainingProgressForUser $sync): void
    {
        $synced = 0; $failed = 0;

        User::role('agent')
            ->whereNotNull('excel_id')
            ->each(function (User $agent) use ($client, $sync, &$synced, &$failed) {
                try {
                    $sync->handle($agent, $client);
                    $synced++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('Training progress sync failed.', ['user_id' => $agent->id]);
                }
            });

        Log::info('Training progress sync finished.', compact('synced', 'failed'));
    }
}
```

`updateOrCreate` keyed on `(user_id, enrollment_id)` makes the sync
idempotent — re-running it never duplicates records, it just refreshes
`metadata`/`synced_at`.

## 4. Wiring it up

### Manual trigger (controller + route)

```php
public function pullAgents(Request $request): RedirectResponse
{
    Gate::authorize('create', User::class);
    PullAgentsFromCustomerApiJob::dispatch($request->user(), $progressId);
    return back();
}
```

Give the frontend a progress id up front (a UUID it generates) so it can
start polling a `pullStatus` endpoint immediately, before the redirect even
completes — don't make it wait for a job id that only exists after dispatch.

### Scheduled trigger (routes/console.php)

```php
Schedule::job(new PullAgentsFromCustomerApiJob)
    ->dailyAt('02:00')
    ->name('sync-agent-roster')
    ->onOneServer();

Schedule::job(new SyncAllAgentsTrainingProgressJob)
    ->everyFourHours()
    ->name('sync-training-progress')
    ->onOneServer();
```

`->onOneServer()` matters the moment you run more than one queue
worker/server — without it, every server would run the scheduled job
simultaneously.

## 5. Config

```php
// config/services.php
'customer_api' => [
    'domain' => env('CUSTOMER_API_DOMAIN'),
    'key' => env('CUSTOMER_API_KEY'),
    'location_id' => env('CUSTOMER_API_LOCATION_ID'),
],
```

## Porting checklist

- [ ] API client class with a paged `eachStudent()`/`eachRecord()` generator
- [ ] Orchestrator job: page the full roster, chunk into batch jobs, count pending creations up front
- [ ] Batch worker job: dedupe by unique key, try/catch per record, log+skip on failure
- [ ] Completion notification/log in the orchestrator's `finally()`
- [ ] Sync-one-user Action class, reused by both the bulk job and any on-demand controller resync
- [ ] Bulk job that loops known users and calls the Action, tolerating individual failures
- [ ] Controller endpoints to trigger both manually, gated by policy/permission
- [ ] `Schedule::job(...)->onOneServer()` entries for both
- [ ] `config/services.php` + `.env` entries for the external API credentials
