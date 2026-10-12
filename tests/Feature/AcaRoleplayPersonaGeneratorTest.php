<?php

use App\Enums\Outcome;
use App\Services\AcaRoleplayPersonaGenerator;
use App\Services\AcaRoleplayScript;
use Random\Engine\Mt19937;
use Random\Randomizer;

function seededAcaGenerator(int $seed): AcaRoleplayPersonaGenerator
{
    return new AcaRoleplayPersonaGenerator(app(AcaRoleplayScript::class), new Randomizer(new Mt19937($seed)));
}

test('levels 1 and 2 are always transferable, with no disqualifier', function (int $level) {
    foreach (range(1, 200) as $seed) {
        $generated = seededAcaGenerator($seed)->generate($level);

        expect($generated['outcome'])->toBe(Outcome::Transfer)
            ->and($generated['persona']['dq_trap'])->toBeNull();
    }
})->with([1, 2]);

test('consumers are working age, and only Medicare disqualifiers are over 65', function () {
    foreach (range(1, 300) as $seed) {
        $persona = seededAcaGenerator($seed)->generate(5)['persona'];

        [$min, $max] = ($persona['dq_trap']['id'] ?? null) === 'medicare' ? [66, 78] : [19, 64];

        expect($persona['age'])->toBeBetween($min, $max);
    }
});

test('a consumer with a disqualifier never raises a Marketplace objection', function () {
    foreach (range(1, 300) as $seed) {
        $persona = seededAcaGenerator($seed)->generate(5)['persona'];

        if ($persona['dq_trap'] === null) {
            continue;
        }

        expect($persona['coverage']['has_marketplace_plan'])->toBeFalse()
            ->and($persona['objections'])->not->toContain('already_have_marketplace');

        if ($persona['dq_trap']['id'] !== 'private') {
            expect($persona['objections'])->not->toContain('insurance_not_familiar');
        }
    }
});

test('a qualified consumer\'s coverage matches the objections they raise', function () {
    foreach (range(1, 300) as $seed) {
        $persona = seededAcaGenerator($seed)->generate(4)['persona'];

        if ($persona['dq_trap'] !== null) {
            continue;
        }

        if (in_array('insurance_not_familiar', $persona['objections'], true)) {
            expect($persona['coverage']['carrier'])->toBeIn(AcaRoleplayPersonaGenerator::CARRIERS);
        }

        if (in_array('already_have_marketplace', $persona['objections'], true)) {
            expect($persona['coverage']['has_marketplace_plan'])->toBeTrue();
        }

        if (in_array('wrong_state', $persona['objections'], true)) {
            expect($persona['actual_state'])->not->toBe($persona['lead']['state']);
        }
    }
});
