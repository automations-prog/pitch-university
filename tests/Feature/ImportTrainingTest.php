<?php

use App\Models\CourseModule;
use App\Models\CourseTrack;
use Illuminate\Support\Facades\File;

/**
 * A minimal valid module file.
 *
 * @param  array<int, string>  $lessonIds
 * @return array<string, mixed>
 */
function moduleFile(string $id, array $lessonIds): array
{
    return [
        'id' => $id,
        'title' => str($id)->headline()->toString(),
        'lessons' => collect($lessonIds)->map(fn (string $lessonId) => [
            'id' => $lessonId,
            'title' => $lessonId,
            'blocks' => [['type' => 'text', 'html' => '<p>Hi</p>']],
        ])->all(),
        'quiz' => ['pass_pct' => 80, 'questions' => [
            ['id' => 'q1', 'question' => 'Q?', 'choices' => ['A', 'B'], 'answer_index' => 0],
        ]],
    ];
}

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/training-import'));
});

test('it imports the bundled training tracks', function () {
    $this->artisan('training:import')->assertSuccessful();

    $track = CourseTrack::where('slug', 'medicare-fronting')->sole();
    $module = $track->modules()->where('slug', 'welcome')->sole();

    expect($track->name)->toBe('Medicare Fronting')
        ->and($module->title)->toBe('Welcome & Your Mission')
        ->and($module->passPercentage())->toBe(80)
        ->and($module->quiz['questions'])->toHaveCount(10)
        ->and($module->lessons->pluck('slug')->all())->toBe(['welcome_1', 'welcome_2', 'welcome_3', 'welcome_4']);

    $medicare101 = $track->modules()->where('slug', 'medicare_101')->sole();

    expect($track->modules->pluck('slug')->all())->toBe(['welcome', 'medicare_101', 'the_script', 'qualify_dq', 'rebuttals', 'real_calls', 'compliance', 'dispositions_transfers', 'dialer_conduct'])
        ->and($medicare101->title)->toBe('Medicare & the Product')
        ->and($medicare101->badge['name'])->toBe('Medicare Pro')
        ->and($medicare101->quiz['questions'])->toHaveCount(10)
        ->and($medicare101->lessons->pluck('slug')->all())->toBe(['m101_1', 'm101_2', 'm101_4', 'm101_5']);

    $theScript = $track->modules()->where('slug', 'the_script')->sole();

    expect($theScript->title)->toBe('The Script, Word for Word')
        ->and($theScript->quiz['questions'])->toHaveCount(12)
        ->and($theScript->lessons->pluck('slug')->all())->toBe(['script_1', 'script_2', 'script_3', 'script_4', 'script_5'])
        ->and($theScript->lessons->first()->blocks[1])->toMatchArray(['type' => 'script', 'title' => 'Opening']);

    $qualifyDq = $track->modules()->where('slug', 'qualify_dq')->sole();

    expect($qualifyDq->title)->toBe('Qualifying & Disqualifying')
        ->and($qualifyDq->badge['name'])->toBe('Sharp Qualifier')
        ->and($qualifyDq->quiz['questions'])->toHaveCount(12)
        ->and($qualifyDq->lessons->pluck('slug')->all())->toBe(['qdq_1', 'qdq_2', 'qdq_3', 'qdq_4']);

    $rebuttals = $track->modules()->where('slug', 'rebuttals')->sole();

    expect($rebuttals->title)->toBe('Objections & Rebuttals')
        ->and($rebuttals->badge['name'])->toBe('Objection Crusher')
        ->and($rebuttals->quiz['questions'])->toHaveCount(13)
        ->and($rebuttals->lessons->pluck('slug')->all())->toBe(['reb_1', 'reb_2', 'reb_3', 'reb_4', 'reb_5'])
        ->and($rebuttals->lessons[1]->blocks[1])->toMatchArray(['type' => 'rebuttal', 'objection' => "I'm not interested"]);

    $realCalls = $track->modules()->where('slug', 'real_calls')->sole();

    expect($realCalls->title)->toBe('Learn From Real Calls')
        ->and($realCalls->badge['name'])->toBe('Film Room')
        ->and($realCalls->quiz['questions'])->toHaveCount(17)
        ->and($realCalls->lessons)->toHaveCount(11)
        ->and($realCalls->lessons->first()->slug)->toBe('real_1')
        ->and($realCalls->lessons->last()->slug)->toBe('real_11');

    $compliance = $track->modules()->where('slug', 'compliance')->sole();

    expect($compliance->title)->toBe('Compliance')
        ->and($compliance->badge['name'])->toBe('Compliance Champ')
        ->and($compliance->quiz['questions'])->toHaveCount(11)
        ->and($compliance->lessons->pluck('slug')->all())->toBe(['comp_1', 'comp_2', 'comp_3', 'comp_4']);

    $dispositions = $track->modules()->where('slug', 'dispositions_transfers')->sole();

    expect($dispositions->title)->toBe('Dispositions, Transfers & Callbacks')
        ->and($dispositions->badge['name'])->toBe('Clean Coder')
        ->and($dispositions->quiz['questions'])->toHaveCount(12)
        ->and($dispositions->lessons->pluck('slug')->all())->toBe(['disp_1', 'disp_2', 'disp_3', 'disp_4']);

    $dialerConduct = $track->modules()->where('slug', 'dialer_conduct')->sole();

    expect($dialerConduct->title)->toBe('Convoso & Call Conduct')
        ->and($dialerConduct->badge['name'])->toBe('Dialer Ready')
        ->and($dialerConduct->quiz['questions'])->toHaveCount(10)
        ->and($dialerConduct->lessons->pluck('slug')->all())->toBe(['dial_1', 'dial_2', 'dial_3', 'dial_4']);
});

test('re-importing updates the track and removes deleted modules and lessons without duplicating', function () {
    $directory = storage_path('framework/testing/training-import');
    File::ensureDirectoryExists("{$directory}/pre-licensing");

    File::put("{$directory}/pre-licensing/01-intro.json", json_encode(moduleFile('intro', ['intro_1', 'intro_2'])));
    File::put("{$directory}/pre-licensing/02-rules.json", json_encode(moduleFile('rules', ['rules_1'])));
    $this->artisan('training:import', ['--path' => $directory])->assertSuccessful();

    File::put("{$directory}/pre-licensing/track.json", json_encode(['name' => 'Pre-Licensing', 'description' => 'Exam prep']));
    File::put("{$directory}/pre-licensing/01-intro.json", json_encode(moduleFile('intro', ['intro_2'])));
    File::delete("{$directory}/pre-licensing/02-rules.json");
    $this->artisan('training:import', ['--path' => $directory])->assertSuccessful();

    $track = CourseTrack::sole();
    expect($track->slug)->toBe('pre-licensing')
        ->and($track->name)->toBe('Pre-Licensing')
        ->and($track->description)->toBe('Exam prep')
        ->and($track->modules->pluck('slug')->all())->toBe(['intro'])
        ->and($track->modules->first()->lessons->pluck('slug')->all())->toBe(['intro_2']);
});

test('the same module id can be used in different tracks', function () {
    $directory = storage_path('framework/testing/training-import');
    File::ensureDirectoryExists("{$directory}/aca-fronting");
    File::ensureDirectoryExists("{$directory}/medicare-fronting");
    File::put("{$directory}/aca-fronting/01-welcome.json", json_encode(moduleFile('welcome', ['a'])));
    File::put("{$directory}/medicare-fronting/01-welcome.json", json_encode(moduleFile('welcome', ['b'])));

    $this->artisan('training:import', ['--path' => $directory])->assertSuccessful();

    expect(CourseTrack::count())->toBe(2)
        ->and(CourseModule::where('slug', 'welcome')->count())->toBe(2);
});

test('it imports the bundled final exam with its sections in file order', function () {
    $this->artisan('training:import')->assertSuccessful();

    $exam = CourseTrack::where('slug', 'medicare-fronting')->sole()->exam;

    expect($exam->title)->toBe('Final Exam')
        ->and($exam->sectionKeys())->toBe(['product', 'script'])
        ->and($exam->section('product')['title'])->toBe('Product Quiz')
        ->and($exam->questionCount('product'))->toBe(20)
        ->and($exam->section('product')['pool'])->toHaveCount(42)
        ->and($exam->questionCount('script'))->toBe(20)
        ->and($exam->section('script')['pool'])->toHaveCount(57);
});

test('the exam file is optional and removing it removes the exam', function () {
    $directory = storage_path('framework/testing/training-import');
    File::ensureDirectoryExists("{$directory}/pre-licensing");
    File::put("{$directory}/pre-licensing/01-intro.json", json_encode(moduleFile('intro', ['intro_1'])));
    File::put("{$directory}/pre-licensing/exam.json", json_encode(['sections' => [
        'basics' => ['pass_pct' => 80, 'draw' => 1, 'pool' => [
            ['id' => 'b1', 'question' => 'Q?', 'choices' => ['A', 'B'], 'answer_index' => 0],
        ]],
    ]]));

    $this->artisan('training:import', ['--path' => $directory])->assertSuccessful();

    $track = CourseTrack::sole();
    expect($track->modules->pluck('slug')->all())->toBe(['intro'])
        ->and($track->exam->title)->toBe('Final Exam')
        ->and($track->exam->section('basics')['title'])->toBe('Basics');

    File::delete("{$directory}/pre-licensing/exam.json");
    $this->artisan('training:import', ['--path' => $directory])->assertSuccessful();

    expect($track->fresh()->exam)->toBeNull();
});

test('it rejects an exam with duplicate question ids', function () {
    $directory = storage_path('framework/testing/training-import');
    File::ensureDirectoryExists("{$directory}/broken");
    File::put("{$directory}/broken/01-intro.json", json_encode(moduleFile('intro', ['intro_1'])));
    $question = ['id' => 'dup', 'question' => 'Q?', 'choices' => ['A', 'B'], 'answer_index' => 0];
    File::put("{$directory}/broken/exam.json", json_encode(['sections' => [
        'basics' => ['pass_pct' => 80, 'draw' => 1, 'pool' => [$question, $question]],
    ]]));

    $this->artisan('training:import', ['--path' => $directory])->assertFailed();

    expect(CourseTrack::count())->toBe(0);
});

test('it rejects an invalid module file without importing anything', function () {
    $directory = storage_path('framework/testing/training-import');
    File::ensureDirectoryExists("{$directory}/broken");
    File::put("{$directory}/broken/01-broken.json", json_encode(['id' => 'broken', 'title' => 'Broken']));

    $this->artisan('training:import', ['--path' => $directory])->assertFailed();

    expect(CourseTrack::count())->toBe(0);
});
