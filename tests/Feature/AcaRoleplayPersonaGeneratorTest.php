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

test('from level 3, consumers with a plan name a carrier we see most', function (int $level) {
    $carriers = [];

    foreach (range(1, 300) as $seed) {
        $persona = seededAcaGenerator($seed)->generate($level)['persona'];

        if ($persona['coverage']['has_marketplace_plan'] || ($persona['dq_trap']['id'] ?? null) === 'private') {
            $carriers[] = $persona['coverage']['carrier'];
        }
    }

    expect($carriers)->not->toBeEmpty()
        ->each->toBeIn(['Oscar', 'Blue Cross', 'Ambetter']);
})->with([3, 4]);

test('the hardest level only hides disqualifiers behind vague wording', function () {
    $traps = [];

    foreach (range(1, 300) as $seed) {
        $traps[] = seededAcaGenerator($seed)->generate(5)['persona']['dq_trap']['id'] ?? null;
    }

    expect(array_values(array_unique(array_filter($traps))))->each->toBeIn(['spousal_work', 'private', 'state_insurance', 'ssdi'])
        ->and(array_filter($traps))->not->toBeEmpty();
});

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

test('every level 4 and 5 consumer raises at least one of the drilled objections', function (int $level) {
    $focus = config('roleplay.aca.focus_objections');

    foreach (range(1, 300) as $seed) {
        $objections = seededAcaGenerator($seed)->generate($level)['persona']['objections'];

        expect(array_intersect($objections, $focus))->not->toBeEmpty();
    }
})->with([4, 5]);

test('how they got my information can come up from level 2', function () {
    $drawn = collect(range(1, 300))
        ->flatMap(fn (int $seed): array => seededAcaGenerator($seed)->generate(2)['persona']['objections']);

    expect($drawn)->toContain('how_got_info');
});

test('a consumer who doesn\'t want to change their insurance has a plan to change', function () {
    foreach (range(1, 300) as $seed) {
        $persona = seededAcaGenerator($seed)->generate(4)['persona'];

        if ($persona['dq_trap'] === null && in_array('dont_want_change', $persona['objections'], true)) {
            expect($persona['coverage']['has_marketplace_plan'])->toBeTrue();
        }
    }
});
