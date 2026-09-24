<?php

namespace App\Console\Commands;

use App\Models\CourseTrack;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Imports training tracks from disk. Each sub-directory of the data path is a
 * track (its name is the track slug) holding an optional `track.json` with the
 * track's name/description, an optional `exam.json` final exam, and one JSON
 * file per module, ordered by filename.
 */
#[Signature('training:import {--path= : Directory containing one folder per training track}')]
#[Description('Import (or re-import) the training tracks and their modules from JSON files')]
class ImportTraining extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = $this->option('path') ?: database_path('data/tracks');

        $trackDirectories = collect(File::directories($directory))->sort()->values();

        if ($trackDirectories->isEmpty()) {
            $this->error("No track folders found in {$directory}.");

            return self::FAILURE;
        }

        $tracks = [];

        foreach ($trackDirectories as $trackDirectory) {
            $track = $this->readTrack($trackDirectory);

            if ($track === null) {
                return self::FAILURE;
            }

            $tracks[] = $track;
        }

        foreach ($tracks as $position => $track) {
            $this->importTrack($track, $position);

            $this->info("Imported track [{$track['slug']}] with ".count($track['modules']).' modules.');
        }

        return self::SUCCESS;
    }

    /**
     * Read and validate every file in a track folder.
     *
     * @return array{slug: string, name: string, description: string|null, modules: array<int, array<string, mixed>>, exam: array<string, mixed>|null}|null
     */
    private function readTrack(string $trackDirectory): ?array
    {
        $slug = basename($trackDirectory);
        $metadata = File::exists("{$trackDirectory}/track.json")
            ? json_decode(File::get("{$trackDirectory}/track.json"), true)
            : [];

        $moduleFiles = collect(File::glob("{$trackDirectory}/*.json"))
            ->reject(fn (string $file) => in_array(basename($file), ['track.json', 'exam.json'], true))
            ->sort()
            ->values();

        if ($moduleFiles->isEmpty()) {
            $this->error("{$slug}: the track has no module files.");

            return null;
        }

        $modules = [];

        foreach ($moduleFiles as $file) {
            $data = json_decode(File::get($file), true);

            try {
                $this->validateModule($data);
            } catch (ValidationException $exception) {
                $this->error("{$slug}/".basename($file).': '.$exception->validator->errors()->first());

                return null;
            }

            $modules[] = $data;
        }

        $exam = null;

        if (File::exists("{$trackDirectory}/exam.json")) {
            $exam = json_decode(File::get("{$trackDirectory}/exam.json"), true);

            try {
                $this->validateExam($exam);
            } catch (ValidationException $exception) {
                $this->error("{$slug}/exam.json: ".$exception->validator->errors()->first());

                return null;
            }
        }

        return [
            'slug' => $slug,
            'name' => $metadata['name'] ?? str($slug)->headline()->toString(),
            'description' => $metadata['description'] ?? null,
            'modules' => $modules,
            'exam' => $exam,
        ];
    }

    /**
     * @param  mixed  $data
     *
     * @throws ValidationException
     */
    private function validateExam($data): void
    {
        Validator::make(is_array($data) ? $data : [], [
            'title' => ['nullable', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['nullable', 'string'],
            'sections.*.pass_pct' => ['required', 'integer', 'between:1,100'],
            'sections.*.draw' => ['required', 'integer', 'min:1'],
            'sections.*.pool' => ['required', 'array', 'min:1'],
            'sections.*.pool.*.id' => ['required', 'string'],
            'sections.*.pool.*.question' => ['required', 'string'],
            'sections.*.pool.*.choices' => ['required', 'array', 'min:2'],
            'sections.*.pool.*.answer_index' => ['required', 'integer', 'min:0'],
        ])->after(function ($validator) use ($data) {
            foreach ($data['sections'] ?? [] as $key => $section) {
                $ids = array_column($section['pool'] ?? [], 'id');

                if (count($ids) !== count(array_unique($ids))) {
                    $validator->errors()->add("sections.{$key}.pool", "The {$key} pool has duplicate question ids.");
                }
            }
        })->validate();
    }

    /**
     * @param  mixed  $data
     *
     * @throws ValidationException
     */
    private function validateModule($data): void
    {
        Validator::make(is_array($data) ? $data : [], [
            'id' => ['required', 'string'],
            'title' => ['required', 'string'],
            'summary' => ['nullable', 'string'],
            'est_minutes' => ['nullable', 'integer'],
            'badge' => ['nullable', 'array'],
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.id' => ['required', 'string', 'distinct'],
            'lessons.*.title' => ['required', 'string'],
            'lessons.*.blocks' => ['required', 'array'],
            'lessons.*.blocks.*.type' => ['required', 'string'],
            'quiz.pass_pct' => ['required', 'integer', 'between:1,100'],
            'quiz.questions' => ['required', 'array', 'min:1'],
            'quiz.questions.*.id' => ['required', 'string', 'distinct'],
            'quiz.questions.*.question' => ['required', 'string'],
            'quiz.questions.*.choices' => ['required', 'array', 'min:2'],
            'quiz.questions.*.answer_index' => ['required', 'integer', 'min:0'],
        ])->validate();
    }

    /**
     * Sync a track and its modules/lessons with the files. Modules and lessons
     * that are no longer in the files are removed.
     *
     * @param  array{slug: string, name: string, description: string|null, modules: array<int, array<string, mixed>>, exam: array<string, mixed>|null}  $data
     */
    private function importTrack(array $data, int $position): void
    {
        DB::transaction(function () use ($data, $position) {
            $track = CourseTrack::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'description' => $data['description'], 'position' => $position],
            );

            foreach ($data['modules'] as $modulePosition => $moduleData) {
                $module = $track->modules()->updateOrCreate(
                    ['slug' => $moduleData['id']],
                    [
                        'title' => $moduleData['title'],
                        'summary' => $moduleData['summary'] ?? null,
                        'est_minutes' => $moduleData['est_minutes'] ?? 0,
                        'badge' => $moduleData['badge'] ?? null,
                        'quiz' => $moduleData['quiz'],
                        'position' => $modulePosition,
                    ],
                );

                foreach ($moduleData['lessons'] as $lessonPosition => $lesson) {
                    $module->lessons()->updateOrCreate(
                        ['slug' => $lesson['id']],
                        [
                            'title' => $lesson['title'],
                            'est_minutes' => $lesson['est_minutes'] ?? 0,
                            'blocks' => $lesson['blocks'],
                            'position' => $lessonPosition,
                        ],
                    );
                }

                $module->lessons()->whereNotIn('slug', array_column($moduleData['lessons'], 'id'))->delete();
            }

            $track->modules()->whereNotIn('slug', array_column($data['modules'], 'id'))->delete();

            if ($data['exam'] === null) {
                $track->exam()->delete();

                return;
            }

            // Sections are stored as an ordered list: JSON columns don't keep object key order.
            $sections = [];

            foreach ($data['exam']['sections'] as $key => $section) {
                $sections[] = [
                    'key' => (string) $key,
                    ...$section,
                    'title' => $section['title'] ?? str((string) $key)->headline()->toString(),
                ];
            }

            $track->exam()->updateOrCreate([], [
                'title' => $data['exam']['title'] ?? 'Final Exam',
                'sections' => $sections,
            ]);
        });
    }
}
