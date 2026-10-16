<?php

namespace App\Concerns;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseTrack;

/**
 * How tracks, modules and lessons are sent to the training pages.
 */
trait PresentsCourseContent
{
    /**
     * @return array{slug: string, name: string, description: string|null}
     */
    protected function trackData(CourseTrack $track): array
    {
        return [
            'slug' => $track->slug,
            'name' => $track->name,
            'description' => $track->description,
        ];
    }

    /**
     * @return array{slug: string, title: string, summary: string|null, est_minutes: int, badge: array<string, string>|null}
     */
    protected function moduleData(CourseModule $module): array
    {
        return [
            'slug' => $module->slug,
            'title' => $module->title,
            'summary' => $module->summary,
            'est_minutes' => $module->est_minutes,
            'badge' => $module->badge,
        ];
    }

    /**
     * @return array{slug: string, title: string, est_minutes: int}
     */
    protected function lessonData(CourseLesson $lesson): array
    {
        return [
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'est_minutes' => $lesson->est_minutes,
        ];
    }
}
