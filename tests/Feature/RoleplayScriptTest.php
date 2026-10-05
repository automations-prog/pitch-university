<?php

use App\Services\RoleplayScript;
use Illuminate\Support\Facades\File;

test('the docs parse into the content the roleplay page depends on', function () {
    $content = (new RoleplayScript)->parse();

    expect(array_column($content['scriptSections'], 'id'))->toBe([
        'opening', 'double_confirm_the_card', 'medicaid', 'state_and_zip',
        'work_va_insurance', RoleplayScript::TRANSFER_ASK_SECTION, 'cold_transfer',
    ])
        ->and(array_column($content['deliveryCriteria'], 'key'))->toBe(RoleplayScript::DELIVERY_CRITERIA)
        ->and($content['rebuttals'])->toHaveCount(13)
        ->and($content['objections'])->toHaveCount(24)
        ->and($content['dqTraps'])->toHaveCount(6)
        ->and($content['quirks'])->toHaveCount(17)
        ->and($content['complianceGuidelines'])->toHaveCount(10)
        ->and(array_column($content['dispositions'], 'id'))->toContain('transfer', 'dq', 'dnc')
        ->and($content['levels'][4]['outcomeMix'])->toBe(['transfer' => 61, 'dq' => 14, 'dnc' => 25]);
});

test('each objection resolves to the rebuttal the doc quotes from the script', function () {
    $script = new RoleplayScript;

    expect($script->objection('never_received_card')['rebuttal']['title'])->toBe("I've already got it")
        ->and($script->objection('transfer_no')['rebuttal']['title'])->toBe('EXTRA NOT INTERESTED REBUTTALS')
        ->and($script->objection('has_different_card')['rebuttal'])->toBe([
            'title' => 'Script line',
            'lines' => ['Okay, no problem, but you DO have your Medicare number, correct?'],
        ])
        ->and($script->objection('who_are_you')['consumerLines'][3])->toBe('Why the f*** are you calling me?');
});

test('the parser fails loudly when a heading it depends on is renamed', function (string $file, string $from, string $to) {
    $directory = storage_path('framework/testing/roleplay-'.uniqid());
    File::copyDirectory(base_path('ai-roleplay'), $directory);
    File::put("{$directory}/{$file}", str_replace($from, $to, File::get("{$directory}/{$file}")));

    try {
        expect(fn () => (new RoleplayScript($directory))->parse())
            ->toThrow(RuntimeException::class, 'Was a heading renamed?');
    } finally {
        File::deleteDirectory($directory);
    }
})->with([
    'rebuttals' => ['medicare_script.md', '# Medicare Rebuttals', '# Rebuttals'],
    'transfer ask' => ['medicare_script.md', '## Ask for the transfer', '## Transfer'],
    'dispositions' => ['medicare_script.md', '## Dispositions', '## Codes'],
    'quirks' => ['OBJECTIONS_AND_PERSONAS.md', '## Quirks', '## Habits'],
    'outcome mix' => ['OBJECTIONS_AND_PERSONAS.md', '## Outcome mix by level', '## Outcomes'],
    'objection section' => ['OBJECTIONS_AND_PERSONAS.md', '### `busy`', '### busy'],
    'guide criterion' => ['guide.md', 'Dead air:', 'Silence:'],
]);
