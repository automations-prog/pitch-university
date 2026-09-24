<?php

namespace App\Models;

use Database\Factories\CourseExamRetakeGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An extra attempt at an exam section, granted by an admin (e.g. after an appeal).
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_exam_id
 * @property string $section
 * @property int|null $granted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'course_exam_id', 'section', 'granted_by'])]
class CourseExamRetakeGrant extends Model
{
    /** @use HasFactory<CourseExamRetakeGrantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return BelongsTo<CourseExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(CourseExam::class, 'course_exam_id');
    }
}
