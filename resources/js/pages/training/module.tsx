import { Head, Link, setLayoutProps } from '@inertiajs/react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import { CourseProgressBar } from '@/components/course-progress-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { brandButtonClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronRight,
    Circle,
    Clock,
    ClipboardList,
    Lock,
} from 'lucide-react';
import type {
    CourseLessonSummary,
    CourseModule,
    CourseTrack,
    CourseModuleProgress,
} from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    progress: CourseModuleProgress;
    lessons: (CourseLessonSummary & { is_complete: boolean })[];
    questionCount: number;
    passPercentage: number;
};

export default function TrainingModule({
    track,
    module,
    progress,
    lessons,
    questionCount,
    passPercentage,
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
        ],
    });

    const nextLesson =
        lessons.find((lesson) => !lesson.is_complete) ?? lessons[0];

    return (
        <>
            <Head title={module.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={TrainingController.showTrack(track.slug)}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to {track.name}
                    </Link>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {module.badge?.icon} {module.title}
                        </h2>
                        {progress.is_passed && (
                            <Badge variant="outline" className="gap-1">
                                <CheckCircle2 className="size-3 text-emerald-600" />
                                {module.badge?.name ?? 'Passed'}
                            </Badge>
                        )}
                    </div>
                    {module.summary && (
                        <p className="text-muted-foreground max-w-3xl text-sm">
                            {module.summary}
                        </p>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Lessons</CardTitle>
                            <CardDescription>
                                {progress.completed_lessons} of{' '}
                                {progress.total_lessons} complete ·{' '}
                                {module.est_minutes} min total
                            </CardDescription>
                            <CourseProgressBar
                                value={progress.completed_lessons}
                                max={progress.total_lessons}
                            />
                        </CardHeader>
                        <CardContent className="p-0">
                            <ul className="divide-y border-t">
                                {lessons.map((lesson, index) => (
                                    <li key={lesson.slug}>
                                        <Link
                                            href={TrainingController.showLesson(
                                                [
                                                    track.slug,
                                                    module.slug,
                                                    lesson.slug,
                                                ],
                                            )}
                                            className="hover:bg-muted/50 flex items-center gap-3 px-6 py-4 transition-colors"
                                        >
                                            {lesson.is_complete ? (
                                                <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
                                            ) : (
                                                <Circle className="text-muted-foreground size-5 shrink-0" />
                                            )}
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">
                                                    {index + 1}. {lesson.title}
                                                </p>
                                                <p className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                                    <Clock className="size-3" />
                                                    {lesson.est_minutes} min
                                                </p>
                                            </div>
                                            <ChevronRight className="text-muted-foreground size-4" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        {nextLesson && !progress.is_quiz_unlocked && (
                            <Button
                                asChild
                                className={`w-full ${brandButtonClass}`}
                            >
                                <Link
                                    href={TrainingController.showLesson([
                                        track.slug,
                                        module.slug,
                                        nextLesson.slug,
                                    ])}
                                >
                                    {progress.completed_lessons === 0
                                        ? 'Start first lesson'
                                        : 'Continue learning'}
                                </Link>
                            </Button>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ClipboardList className="size-5" />
                                    Module quiz
                                </CardTitle>
                                <CardDescription>
                                    {questionCount} questions · {passPercentage}
                                    % to pass
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {progress.best_score !== null && (
                                    <p className="text-sm">
                                        Best score:{' '}
                                        <span className="font-semibold">
                                            {progress.best_score}%
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            ({progress.attempts_count}{' '}
                                            {progress.attempts_count === 1
                                                ? 'attempt'
                                                : 'attempts'}
                                            )
                                        </span>
                                    </p>
                                )}
                                {progress.is_quiz_unlocked ? (
                                    <Button
                                        asChild
                                        className={`w-full ${brandButtonClass}`}
                                    >
                                        <Link
                                            href={TrainingController.showQuiz([
                                                track.slug,
                                                module.slug,
                                            ])}
                                        >
                                            {progress.attempts_count > 0
                                                ? 'View quiz'
                                                : 'Take the quiz'}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        disabled
                                    >
                                        <Lock />
                                        Complete every lesson to unlock
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
