import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Clock } from 'lucide-react';
import TrainingModuleController from '@/actions/App/Http/Controllers/TrainingModuleController';
import { CourseProgressBar } from '@/components/course-progress-bar';
import { CourseBlocks } from '@/components/training-blocks';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { brandButtonClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as trainingModulesIndex } from '@/routes/training-modules';
import type {
    CourseLesson,
    CourseLessonSummary,
    CourseModule,
    CourseTrack,
} from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    lesson: Omit<CourseLesson, 'is_complete'>;
    lessonNumber: number;
    totalLessons: number;
    previousLesson: CourseLessonSummary | null;
    nextLesson: CourseLessonSummary | null;
};

export default function TrainingModulesLesson({
    track,
    module,
    lesson,
    lessonNumber,
    totalLessons,
    previousLesson,
    nextLesson,
}: Props) {
    const { auth } = usePage().props;
    const moduleHref = TrainingModuleController.showModule([
        track.slug,
        module.slug,
    ]);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training Modules', href: trainingModulesIndex() },
            {
                title: track.name,
                href: TrainingModuleController.showTrack(track.slug),
            },
            { title: module.title, href: moduleHref },
            {
                title: lesson.title,
                href: TrainingModuleController.showLesson([
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
                        href={moduleHref}
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
                        <CourseBlocks
                            key={lesson.slug}
                            blocks={lesson.blocks}
                            storageKey={`training-modules-check:${auth.user.id}:${track.slug}:${module.slug}:${lesson.slug}`}
                        />
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {previousLesson ? (
                        <Button asChild variant="outline">
                            <Link
                                href={TrainingModuleController.showLesson([
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

                    <Button
                        asChild
                        className={`w-full sm:w-auto ${brandButtonClass}`}
                    >
                        <Link
                            href={
                                nextLesson
                                    ? TrainingModuleController.showLesson([
                                          track.slug,
                                          module.slug,
                                          nextLesson.slug,
                                      ])
                                    : moduleHref
                            }
                        >
                            {nextLesson ? 'Next lesson' : 'Back to module'}
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
