<?php

use App\Services\MealsRoleplayScript;
use Illuminate\Support\Facades\File;

test('the Meals docs parse into the content the roleplay page depends on', function () {
    $content = (new MealsRoleplayScript)->parse();

    expect(array_column($content['scriptSections'], 'id'))->toBe([
        'opening', 'medicaid_branch', 'borough_and_zip', 'double_confirm',
        MealsRoleplayScript::TRANSFER_ASK_SECTION, 'warm_handoff',
    ])
        ->and(array_column($content['deliveryCriteria'], 'key'))->toBe(MealsRoleplayScript::DELIVERY_CRITERIA)
        ->and($content['objections'])->toHaveCount(12)
        ->and(array_column($content['dqTraps'], 'id'))->toBe(['no_medicaid', 'copays', 'medicare_only', 'non_qualifying_plan', 'outside_nyc'])
        ->and($content['quirks'])->toHaveCount(13)
        ->and(array_column($content['dispositions'], 'id'))->toBe([
            'transfer', 'dq', 'not_interested', 'dnc', 'call_back', 'hung_up_transfer',
            'dead_air', 'answering_machine', 'wrong_number', 'no_english',
        ])
        ->and($content['dispositions'][1]['description'])->toEndWith('Read the DQ line and end the call.')
        ->and($content['complianceGuidelines'])->toContain('ASK the Medicaid question and get an answer.')
        ->and($content['levels'][2]['outcomeMix'])->toBe(['transfer' => 78, 'dq' => 22, 'dnc' => 0])
        ->and($content['levels'][4]['outcomeMix'])->toBe(['transfer' => 55, 'dq' => 20, 'dnc' => 25])
        ->and($content['forbiddenWords'])->toBe([]);
});

test('every objection resolves to official rebuttal wording from the script', function () {
    $script = new MealsRoleplayScript;

    foreach ($script->content()['objections'] as $objection) {
        expect($objection['rebuttal']['lines'])->not->toBeEmpty()
            ->and($objection['consumerLines'])->not->toBeEmpty();
    }

    expect($script->objection('not_interested')['lostPercent'])->toBe(97.5)
        ->and($script->objection('told_dont_qualify')['rebuttal']['title'])->toBe('What is this / Why are you calling me?');
});

test('no top-agent example line names a consumer or makes a banned claim', function () {
    $library = json_decode(File::get(base_path('ai-roleplay/meals/meals_objections.json')), true);

    foreach ($library['objections'] as $objection) {
        foreach ($objection['top_agent_lines'] as $line) {
            expect($line)->not->toMatch('/\b(costs? \$?\d|you(\'ll| will) have to pay|coverage will change|guarantee)/i');
        }
    }
});

test('the parser fails loudly when a heading it depends on is renamed', function (string $file, string $from, string $to) {
    $directory = storage_path('framework/testing/meals-roleplay-'.uniqid());
    File::copyDirectory(base_path('ai-roleplay/meals'), $directory);
    File::copy(base_path('ai-roleplay/guide.md'), "{$directory}/guide.md");
    File::put("{$directory}/{$file}", str_replace($from, $to, File::get("{$directory}/{$file}")));

    try {
        expect(fn () => (new MealsRoleplayScript($directory))->parse())
            ->toThrow(RuntimeException::class, 'Was a heading renamed?');
    } finally {
        File::deleteDirectory($directory);
    }
})->with([
    'rebuttal heading' => ['script.md', '**Not interested**', '**Not keen**'],
    'transfer ask' => ['script.md', '## Ask for the transfer', '## Transfer'],
    'dispositions' => ['script.md', '**DNC** —', '**Do not call** —'],
    'disqualifiers' => ['OBJECTIONS_AND_PERSONAS.md', '## Disqualifiers', '## DQs'],
]);
