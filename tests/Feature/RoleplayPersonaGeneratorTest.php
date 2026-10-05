<?php

use App\Enums\Outcome;
use App\Services\RoleplayPersonaGenerator;
use App\Services\RoleplayScript;
use Random\Engine\Mt19937;
use Random\Randomizer;

function seededGenerator(int $seed): RoleplayPersonaGenerator
{
    return new RoleplayPersonaGenerator(app(RoleplayScript::class), new Randomizer(new Mt19937($seed)));
}

test('personas only draw objections unlocked at their level, within the level\'s count range', function (int $level) {
    $script = app(RoleplayScript::class);
    [$min, $max] = $script->level($level)['objectionRange'];

    foreach (range(1, 200) as $seed) {
        $objections = seededGenerator($seed)->generate($level)['persona']['objections'];

        expect(count($objections))->toBeGreaterThanOrEqual($min)->toBeLessThanOrEqual($max)
            ->and($objections)->toEqual(array_unique($objections));

        foreach ($objections as $id) {
            expect($script->objection($id)['minLevel'])->toBeLessThanOrEqual($level);
        }
    }
})->with([1, 2, 3, 4, 5]);

test('levels 1 and 2 are always transferable with no DQ trap', function (int $level) {
    foreach (range(1, 200) as $seed) {
        $generated = seededGenerator($seed)->generate($level);

        expect($generated['outcome'])->toBe(Outcome::Transfer)
            ->and($generated['persona']['dq_trap'])->toBeNull();
    }
})->with([1, 2]);

test('a DQ persona always has a trap and other outcomes never do', function () {
    $outcomes = [];

    foreach (range(1, 400) as $seed) {
        $generated = seededGenerator($seed)->generate(5);
        $outcomes[$generated['outcome']->value] = true;

        expect($generated['persona']['dq_trap'] !== null)->toBe($generated['outcome'] === Outcome::Dq);
    }

    expect(array_keys($outcomes))->toEqualCanonicalizing(['transfer', 'dq', 'dnc']);
});

test('quirks start at level 2 and patience is 10 minus the level', function (int $level, bool $hasQuirk) {
    $persona = seededGenerator(7)->generate($level)['persona'];

    expect($persona['quirk'] !== null)->toBe($hasQuirk)
        ->and($persona['patience'])->toBe(10 - $level);
})->with([[1, false], [2, true], [5, true]]);

test('each lead always gets the same voice, matching their gender', function () {
    $voicesByLead = [];

    foreach (range(1, 200) as $seed) {
        $generated = seededGenerator($seed)->generate(1);
        $lead = $generated['persona']['lead'];
        $voicesByLead[$lead['name']][$generated['voice']->value] = true;

        expect($generated['voice']->gender())->toBe($lead['gender'])
            ->and($lead)->not->toHaveKey('voice');
    }

    expect(array_map('count', $voicesByLead))->each->toBe(1)
        ->and(count($voicesByLead))->toBe(8);
});
