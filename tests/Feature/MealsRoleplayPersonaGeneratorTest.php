<?php

use App\Enums\Outcome;
use App\Services\MealsRoleplayPersonaGenerator;
use App\Services\MealsRoleplayScript;
use Random\Engine\Mt19937;
use Random\Randomizer;

function seededMealsGenerator(int $seed): MealsRoleplayPersonaGenerator
{
    return new MealsRoleplayPersonaGenerator(app(MealsRoleplayScript::class), new Randomizer(new Mt19937($seed)));
}

test('levels 1 and 2 are always transferable, with no disqualifier and no borough trap', function (int $level) {
    foreach (range(1, 200) as $seed) {
        $generated = seededMealsGenerator($seed)->generate($level);

        expect($generated['outcome'])->toBe(Outcome::Transfer)
            ->and($generated['persona']['dq_trap'])->toBeNull()
            ->and($generated['persona'])->not->toHaveKey('says_borough');
    }
})->with([1, 2]);

test('the copay fallback comes up often enough to practise', function () {
    $dontKnow = 0;

    foreach (range(1, 500) as $seed) {
        if (seededMealsGenerator($seed)->generate(3)['persona']['medicaid']['status'] === 'dont_know') {
            $dontKnow++;
        }
    }

    expect($dontKnow / 500)->toBeGreaterThan(0.1);
});

test('a consumer\'s Medicaid answer and borough match their disqualifier', function () {
    foreach (range(1, 400) as $seed) {
        $persona = seededMealsGenerator($seed)->generate(5)['persona'];
        $medicaid = $persona['medicaid'];

        match ($persona['dq_trap']['id'] ?? null) {
            null => expect($medicaid['status'])->toBeIn(['yes', 'dont_know', 'names_plan'])
                ->and($medicaid['pays_copays'])->toBeFalse()
                ->and($medicaid['status'] === 'names_plan' ? $medicaid['plan'] : 'Healthfirst')->toBeIn(MealsRoleplayScript::QUALIFYING_PLANS)
                ->and($persona['says_borough'] ?? 'New York')->toBeIn(['New York', 'Long Island City']),
            'no_medicaid' => expect($medicaid['status'])->toBe('no'),
            'copays' => expect($medicaid)->toMatchArray(['status' => 'dont_know', 'pays_copays' => true]),
            'medicare_only' => expect($medicaid['status'])->toBe('medicare_only')
                ->and($persona['age'])->toBeGreaterThanOrEqual(65),
            'non_qualifying_plan' => expect($medicaid['plan'])->toBeIn(MealsRoleplayScript::PARENT_BRANDS),
            'outside_nyc' => expect($persona['says_borough'])->toBeIn(MealsRoleplayPersonaGenerator::OUTSIDE_NYC),
        };

        expect($persona['age'])->toBeBetween(30, 85);

        if (($persona['says_borough'] ?? null) === 'Long Island City') {
            expect($persona['lead']['borough'])->toBe('Queens');
        }
    }
});
