import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import { CourseBlocks } from '@/components/training-blocks';
import { CourseProgressBar } from '@/components/course-progress-bar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import { ArrowLeft, ArrowRight, CheckCircle2, Clock } from 'lucide-react';
import type {
    CourseLesson,
    CourseLessonSummary,
    CourseModule,
    CourseTrack,
} from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    lesson: CourseLesson;
    lessonNumber: number;
    totalLessons: number;
    previousLesson: CourseLessonSummary | null;
    nextLesson: CourseLessonSummary | null;
};

export default function TrainingLesson({
    track,
    module,
    lesson,
    lessonNumber,
    totalLessons,
    previousLesson,
    nextLesson,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training', href: trainingIndex() },
            {
                title: track.name,
                href: TrainingController.showTrack(track.slug),
            },
            {
                title: module.title,
                href: TrainingController.showModule([track.slug, module.slug]),
            },
            {
                title: lesson.title,
                href: TrainingController.showLesson([
                    track.slug,
                    module.slug,
                    lesson.slug,
                ]),
            },
        ],
    });

    return (
        <>
            <Head title={lesson.title} />

            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div className="space-y-3">
                    <Link
                        href={TrainingController.showModule([
                            track.slug,
                            module.slug,
                        ])}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        {module.title}
                    </Link>
                    <div className="text-muted-foreground flex items-center justify-between text-xs">
                        <span>
                            Lesson {lessonNumber} of {totalLessons}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Clock className="size-3" />
                            {lesson.est_minutes} min
                        </span>
                    </div>
                    <CourseProgressBar
                        value={lessonNumber}
                        max={totalLessons}
                    />
                    <h2 className="text-2xl font-semibold tracking-tight">
                        {lesson.title}
                    </h2>
                </div>

                <Card>
                    <CardContent className="py-2">
                        <CourseBlocks blocks={lesson.blocks} />
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {previousLesson ? (
                        <Button asChild variant="outline">
                            <Link
                                href={TrainingController.showLesson([
                                    track.slug,
                                    module.slug,
                                    previousLesson.slug,
                                ])}
                            >
                                <ArrowLeft />
                                Previous
                            </Link>
                        </Button>
                    ) : (
                        <span />
                    )}

                    <Form
                        {...TrainingController.completeLesson.form([
                            track.slug,
                            module.slug,
                            lesson.slug,
                        ])}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing}
                                className={`w-full sm:w-auto ${brandButtonClass}`}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : lesson.is_complete ? (
                                    <CheckCircle2 />
                                ) : null}
                                {lesson.is_complete
                                    ? nextLesson
                                        ? 'Next lesson'
                                        : 'Back to module'
                                    : nextLesson
                                      ? 'Mark complete & continue'
                                      : 'Mark complete & finish'}
                                {!processing && <ArrowRight />}
                            </Button>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
