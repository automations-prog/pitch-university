<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseExam;
use App\Models\User;
use App\Services\ExamProgress;
use App\Services\TrainingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExamRetakeController extends Controller
{
    /**
     * Grant the user one extra attempt at an exam section they are out of attempts on.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'course_exam_id' => ['required', 'integer', Rule::exists('course_exams', 'id')],
            'section' => ['required', 'string'],
        ]);

        $exam = CourseExam::query()->with('track')->whereKey($validated['course_exam_id'])->firstOrFail();

        if (! $exam->hasSection($validated['section'])) {
            throw ValidationException::withMessages(['section' => __('That exam section does not exist.')]);
        }

        $progress = ExamProgress::forTrack($user, $exam, new TrainingProgress($user, $exam->track));

        if (! $progress->isOutOfAttempts($validated['section'])) {
            throw ValidationException::withMessages(['section' => __('A retake can only be granted once all attempts are used and the section is not passed.')]);
        }

        $exam->retakeGrants()->create([
            'user_id' => $user->id,
            'section' => $validated['section'],
            'granted_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retake granted to :name.', ['name' => $user->name])]);

        return back();
    }
}
