import { Head, Link, setLayoutProps } from '@inertiajs/react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import TrainingExamController from '@/actions/App/Http/Controllers/TrainingExamController';
import { CourseProgressBar } from '@/components/course-progress-bar';
import { ExamStatusBadge } from '@/components/exam-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { brandButtonClass, resourceCardClass } from '@/lib/brand-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import { ArrowRight, CheckCircle2, Clock, Lock, Trophy } from 'lucide-react';
import type {
    CourseModule,
    CourseModuleProgress,
    CourseTrack,
    ExamSummary,
} from '@/types';

type ModuleWithProgress = CourseModule & { progress: CourseModuleProgress };

export default function TrainingTrack({
    track,
    modules,
    exam,
}: {
    track: CourseTrack;
    modules: ModuleWithProgress[];
    exam: ExamSummary | null;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training', href: trainingIndex() },
            {
                title: track.name,
                href: TrainingController.showTrack(track.slug),
            },
        ],
    });

    return (
        <>
            <Head title={track.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {track.name}
                        </h2>
                        <p className="text-muted-foreground max-w-3xl text-sm">
                            {track.description ? `${track.description} ` : ''}
                            Work through each module in order. Pass a module's
                            quiz to unlock the next one.
                        </p>
                    </div>
                </div>

                {modules.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No modules have been published yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {modules.map((module, index) => (
                            <ModuleCard
                                key={module.slug}
                                track={track}
                                module={module}
                                number={index + 1}
                            />
                        ))}
                    </div>
                )}

                {exam && <ExamCard track={track} exam={exam} />}
            </div>
        </>
    );
}

function ExamCard({ track, exam }: { track: CourseTrack; exam: ExamSummary }) {
    const totalQuestions = exam.sections.reduce(
        (sum, section) => sum + section.question_count,
        0,
    );

    return (
        <Card
            className={cn(
                'gap-4 border-2',
                exam.is_passed
                    ? 'border-emerald-500/60'
                    : 'border-[#c774ff]/40',
                !exam.is_unlocked && 'opacity-60',
            )}
        >
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div
                            className="flex size-10 items-center justify-center rounded-full text-white"
                            style={{
                                background:
                                    'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                            }}
                        >
                            {exam.is_unlocked ? (
                                <Trophy className="size-5" />
                            ) : (
                                <Lock className="size-4" />
                            )}
                        </div>
                        <div>
                            <CardTitle>{exam.title}</CardTitle>
                            <CardDescription>
                                {exam.sections
                                    .map((section) => section.title)
                                    .join(' + ')}{' '}
                                · {totalQuestions} questions
                            </CardDescription>
                        </div>
                    </div>
                    <ExamStatusBadge status={exam.status} />
                </div>
            </CardHeader>
            <CardFooter className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-muted-foreground text-sm">
                    {exam.is_unlocked
                        ? 'Pass every section to get certified.'
                        : 'Pass every module to unlock the final exam.'}
                </p>
                {exam.is_unlocked ? (
                    <Button asChild className={brandButtonClass}>
                        <Link href={TrainingExamController.show(track.slug)}>
                            {exam.is_passed ? 'View results' : 'Open exam'}
                            <ArrowRight />
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" disabled>
                        <Lock />
                        Locked
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}

function ModuleCard({
    track,
    module,
    number,
}: {
    track: CourseTrack;
    module: ModuleWithProgress;
    number: number;
}) {
    const { progress } = module;

    return (
        <Card
            className={cn(
                'gap-4',
                resourceCardClass,
                !progress.is_unlocked && 'opacity-60',
            )}
        >
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div
                        className="flex size-10 items-center justify-center rounded-full text-lg text-white"
                        style={{
                            background:
                                'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                        }}
                    >
                        {progress.is_unlocked ? (
                            (module.badge?.icon ?? number)
                        ) : (
                            <Lock className="size-4" />
                        )}
                    </div>
                    {progress.is_passed ? (
                        <Badge variant="outline" className="gap-1">
                            <CheckCircle2 className="size-3 text-emerald-600" />
                            {module.badge?.name ?? 'Passed'}
                        </Badge>
                    ) : progress.is_unlocked ? (
                        <Badge variant="secondary">In progress</Badge>
                    ) : (
                        <Badge variant="secondary">Locked</Badge>
                    )}
                </div>
                <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                    Module {number}
                </p>
                <CardTitle>{module.title}</CardTitle>
                {module.summary && (
                    <CardDescription>{module.summary}</CardDescription>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="text-muted-foreground flex items-center gap-4 text-xs">
                    <span className="inline-flex items-center gap-1">
                        <Clock className="size-3" />
                        {module.est_minutes} min
                    </span>
                    <span>
                        {progress.completed_lessons}/{progress.total_lessons}{' '}
                        lessons
                    </span>
                    {progress.best_score !== null && (
                        <span>Best quiz: {progress.best_score}%</span>
                    )}
                </div>
                <CourseProgressBar
                    value={progress.completed_lessons}
                    max={progress.total_lessons}
                />
            </CardContent>
            <CardFooter className="mt-auto">
                {progress.is_unlocked ? (
                    <Button asChild variant="outline" className="w-full">
                        <Link
                            href={TrainingController.showModule([
                                track.slug,
                                module.slug,
                            ])}
                        >
                            {progress.is_passed
                                ? 'Review module'
                                : 'Open module'}
                            <ArrowRight />
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" className="w-full" disabled>
                        <Lock />
                        Pass the previous module to unlock
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}
