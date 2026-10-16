<?php

use App\Services\AcaRoleplayScript;
use Illuminate\Support\Facades\File;

test('the ACA docs parse into the content the roleplay page depends on', function () {
    $content = (new AcaRoleplayScript)->parse();

    expect(array_column($content['scriptSections'], 'id'))->toBe([
        'opening', 'confirm_the_state', 'confirm_no_medicare_medicaid_or_work_insurance',
        'double_confirm_and_age', AcaRoleplayScript::TRANSFER_ASK_SECTION, 'conference_and_warm_handoff',
    ])
        ->and(array_column($content['deliveryCriteria'], 'key'))->toBe(AcaRoleplayScript::DELIVERY_CRITERIA)
        ->and($content['objections'])->toHaveCount(22)
        ->and(array_column($content['dqTraps'], 'id'))->toBe([
            'medicare', 'medicaid', 'work_insurance', 'spousal_work', 'military_va',
            'private', 'state_insurance', 'ssdi', 'non_citizen',
        ])
        ->and($content['quirks'])->toHaveCount(14)
        ->and(array_column($content['dispositions'], 'id'))->toContain(
            'transfer', 'hung_up_transfer', 'not_interested', 'call_back', 'no_agent', 'conference_ending',
            'dq', 'dnc', 'dead_air', 'answering_machine', 'robo', 'wrong_number', 'no_english',
        )
        ->and($content['levels'][2]['outcomeMix'])->toBe(['transfer' => 78, 'dq' => 22, 'dnc' => 0])
        ->and($content['levels'][4]['outcomeMix'])->toBe(['transfer' => 45, 'dq' => 40, 'dnc' => 15])
        ->and($content['forbiddenWords'])->toContain('money', 'cheque', 'how much', 'mortgage');
});

test('every objection resolves to official rebuttal wording from the script', function () {
    $script = new AcaRoleplayScript;

    foreach ($script->content()['objections'] as $objection) {
        expect($objection['rebuttal']['lines'])->not->toBeEmpty()
            ->and($objection['consumerLines'])->not->toBeEmpty();
    }

    expect($script->objection('scam')['rebuttal']['title'])->toBe("This is a scam / spam call / That doesn't sound true")
        ->and($script->objection('hesitation')['rebuttal']['title'])->toBe('Anything not listed above');
});

test('the parser fails loudly when an objection points at a rebuttal heading that is gone', function (string $file, string $from, string $to) {
    $directory = storage_path('framework/testing/aca-roleplay-'.uniqid());
    File::copyDirectory(base_path('ai-roleplay/aca'), $directory);
    File::copy(base_path('ai-roleplay/guide.md'), "{$directory}/guide.md");
    File::put("{$directory}/{$file}", str_replace($from, $to, File::get("{$directory}/{$file}")));

    try {
        expect(fn () => (new AcaRoleplayScript($directory))->parse())
            ->toThrow(RuntimeException::class, 'Was a heading renamed?');
    } finally {
        File::deleteDirectory($directory);
    }
})->with([
    'rebuttal heading' => ['script.md', '**This is a scam / spam call / That doesn\'t sound true**', '**Scam**'],
    'transfer ask' => ['script.md', '## Ask for the transfer', '## Transfer'],
    'dispositions' => ['script.md', '**DNC**:', '**Do not call**:'],
    'disqualifiers' => ['OBJECTIONS_AND_PERSONAS.md', '## The nine disqualifiers', '## DQs'],
]);

test('the drilled objections carry the trainers\' wording and the top agents\' lines', function () {
    $script = new AcaRoleplayScript;

    expect($script->objection('is_it_money')['consumerLines'])->toContain('How much is this?', 'Am I receiving some sort of cheque?')
        ->and($script->objection('dont_want_change')['consumerLines'])->toContain("I don't want to change my insurance.")
        ->and($script->objection('how_got_info')['consumerLines'])->toContain('How did you end up getting my information?')
        ->and($script->objection('how_got_info')['minLevel'])->toBe(2)
        ->and($script->objection('how_got_info')['topAgentLines'])->toHaveCount(2)
        ->and($script->objection('didnt_apply')['topAgentLines'])->toBe([]);
});
