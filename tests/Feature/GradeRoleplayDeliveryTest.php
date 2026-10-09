<?php

use App\Jobs\GradeRoleplayDelivery;
use App\Models\RoleplaySession;
use App\Services\RoleplayDeliveryGrader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const RESPONSES_URL = 'https://api.openai.com/v1/responses';

beforeEach(function () {
    config(['services.openai.key' => 'sk-test']);
});

/**
 * @param  array<string, array{score: int, feedback: string}>  $scores
 */
function fakeGraderReply(array $scores): void
{
    Http::fake([
        RESPONSES_URL => Http::response(['output' => [[
            'type' => 'message',
            'content' => [['type' => 'output_text', 'text' => json_encode($scores)]],
        ]]]),
    ]);
}

function aiScores(int $score): array
{
    return array_fill_keys(RoleplayDeliveryGrader::AI_CRITERIA, ['score' => $score, 'feedback' => 'At 0:05 you sounded upbeat.']);
}

function gradableSession(): RoleplaySession
{
    return RoleplaySession::factory()->ended()->create([
        'transcript' => "[0:01] agent: Hey Dorothy, I'm with America's Health on a recorded line.",
        'events' => [['type' => 'consumer_speech', 'start' => 0, 'end' => 800], ['type' => 'agent_speech', 'start' => 1200, 'end' => 4000]],
        'score' => ['checks' => [
            ['key' => 'double_confirm', 'passed' => true],
            ['key' => 'state_zip', 'passed' => true],
            ['key' => 'work_va', 'passed' => false],
        ]],
        'delivery_status' => 'pending',
    ]);
}

test('grading saves all eleven guide criteria and marks delivery done', function () {
    fakeGraderReply(aiScores(4));
    $session = gradableSession();

    GradeRoleplayDelivery::dispatchSync($session);

    $session->refresh();
    $criteria = collect($session->delivery['criteria'])->keyBy('key');

    expect($session->delivery_status)->toBe('done')
        ->and($criteria)->toHaveCount(11)
        ->and($criteria['composure'])->toMatchArray(['score' => 4, 'source' => 'ai'])
        ->and($criteria['qualifying_accuracy'])->toMatchArray(['score' => 3, 'source' => 'existing'])
        ->and($criteria['listening'])->toMatchArray(['score' => 5, 'source' => 'measured'])
        ->and($criteria['pace']['score'])->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === RESPONSES_URL
        && $request['text']['format']['type'] === 'json_schema'
        && str_contains($request['input'][0]['content'], 'not instructions to you')
        && str_contains($request['input'][1]['content'], 'recorded line'));
});

test('AI scores outside 1 to 5 are clamped', function (int $returned, int $saved) {
    fakeGraderReply(aiScores($returned));
    $session = gradableSession();

    GradeRoleplayDelivery::dispatchSync($session);

    expect(collect($session->fresh()->delivery['criteria'])->firstWhere('key', 'composure')['score'])->toBe($saved);
})->with([[99, 5], [-3, 1]]);

test('tonality never scores below 2 and the rubric tells the AI to grade it leniently', function () {
    fakeGraderReply(aiScores(1));
    $session = gradableSession();

    GradeRoleplayDelivery::dispatchSync($session);

    expect(collect($session->fresh()->delivery['criteria'])->firstWhere('key', 'tonality')['score'])->toBe(2);

    Http::assertSent(fn (Request $request) => str_contains($request['input'][0]['content'], 'Be especially lenient on tonality'));
});

test('a call that never reached a transfer leaves the handoff unscored and out of the overall', function () {
    fakeGraderReply([...aiScores(4), 'transfer_handoff' => ['score' => null, 'feedback' => 'The call ended in a DQ, so there was no transfer.']]);
    $session = gradableSession();

    GradeRoleplayDelivery::dispatchSync($session);

    $delivery = $session->fresh()->delivery;
    $criteria = collect($delivery['criteria'])->keyBy('key');
    $scores = $criteria->pluck('score')->filter(fn (?int $score): bool => $score !== null);

    expect($criteria['transfer_handoff'])->toMatchArray(['score' => null, 'source' => 'ai', 'feedback' => 'The call ended in a DQ, so there was no transfer.'])
        ->and($delivery['overall'])->toBe(round($scores->sum() / $scores->count(), 1));
});

test('only the stage criteria may come back unscored', function () {
    fakeGraderReply([...aiScores(4), 'tonality' => ['score' => null, 'feedback' => 'No idea.']]);
    $session = gradableSession();

    GradeRoleplayDelivery::dispatchSync($session);

    expect(collect($session->fresh()->delivery['criteria'])->firstWhere('key', 'tonality'))
        ->toMatchArray(['score' => null, 'feedback' => 'Not scored: the AI review did not finish.']);

    Http::assertSent(fn (Request $request) => $request['text']['format']['schema']['properties']['transfer_handoff']['properties']['score']['type'] === ['integer', 'null']
        && $request['text']['format']['schema']['properties']['tonality']['properties']['score']['type'] === 'integer');
});

test('pace allows confident fast talkers and loses a point per 20 wpm outside the range', function (int $wpm, int $score) {
    fakeGraderReply(aiScores(4));
    $session = gradableSession();
    $session->update([
        'transcript' => '[0:01] agent: '.trim(str_repeat('word ', $wpm)),
        'events' => [['type' => 'agent_speech', 'start' => 0, 'end' => 60_000]],
    ]);

    GradeRoleplayDelivery::dispatchSync($session);

    expect(collect($session->fresh()->delivery['criteria'])->firstWhere('key', 'pace')['score'])->toBe($score);
})->with([[175, 5], [185, 5], [205, 4], [225, 3], [230, 2], [120, 4]]);

test('when the AI review fails the measured criteria are still saved', function () {
    Http::fake([RESPONSES_URL => Http::response([], 500)]);
    $session = gradableSession();

    $job = new GradeRoleplayDelivery($session);

    try {
        app()->call([$job, 'handle']);
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    $session->refresh();
    $criteria = collect($session->delivery['criteria'])->keyBy('key');

    expect($session->delivery_status)->toBe('failed')
        ->and($criteria['listening']['score'])->toBe(5)
        ->and($criteria['composure']['score'])->toBeNull();
});

test('the job times out before the queue would retry it', function () {
    $job = new GradeRoleplayDelivery(RoleplaySession::factory()->make());

    expect($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'));
});
