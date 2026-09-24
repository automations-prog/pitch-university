import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import ExamRetakeController from '@/actions/App/Http/Controllers/Admin/ExamRetakeController';
import TrainingTrackController from '@/actions/App/Http/Controllers/Admin/TrainingTrackController';
import UserTrackController from '@/actions/App/Http/Controllers/Admin/UserTrackController';
import { CourseProgressBar } from '@/components/course-progress-bar';
import { ExamStatusBadge } from '@/components/exam-status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Lock,
    RotateCcw,
    Trophy,
} from 'lucide-react';
import type {
    CourseModuleProgress,
    ExamSectionSummary,
    ExamSummary,
    UserRole,
} from '@/types';

type QuizAttempt = {
    id: number;
    score_pct: number;
    passed: boolean;
    created_at: string;
};

type ModuleProgress = CourseModuleProgress & {
    slug: string;
    title: string;
    attempts: QuizAttempt[];
};

type TrackProgress = {
    slug: string;
    name: string;
    is_assigned: boolean;
    passed_modules: number;
    total_modules: number;
    modules: ModuleProgress[];
    exam: (ExamSummary & { id: number }) | null;
};

type Props = {
    user: { id: number; name: string; email: string; role: UserRole };
    tracks: TrackProgress[];
    availableTracks: { id: number; name: string; is_assigned: boolean }[];
};

export default function TrainingTrackUser({
    user,
    tracks,
    availableTracks,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training Tracks', href: trainingTracksIndex() },
            {
                title: user.name,
                href: TrainingTrackController.show(user.id),
            },
        ],
    });

    return (
        <>
            <Head title={`${user.name} · Training`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={trainingTracksIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to training tracks
                    </Link>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {user.name}
                        </h2>
                        <Badge variant="secondary" className="capitalize">
                            {user.role}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {user.email}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {tracks.length === 0 ? (
                            <Card>
                                <CardContent className="text-muted-foreground py-10 text-center text-sm">
                                    No tracks are toggled on for this user.
                                </CardContent>
                            </Card>
                        ) : (
                            tracks.map((track) => (
                                <TrackProgressCard
                                    key={track.slug}
                                    userId={user.id}
                                    track={track}
                                />
                            ))
                        )}
                    </div>

                    <TrackToggles
                        userId={user.id}
                        availableTracks={availableTracks}
                    />
                </div>
            </div>
        </>
    );
}

function TrackProgressCard({
    userId,
    track,
}: {
    userId: number;
    track: TrackProgress;
}) {
    const [openModule, setOpenModule] = useState<string | null>(null);

    return (
        <Card>
            <CardHeader className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <CardTitle>{track.name}</CardTitle>
                    {!track.is_assigned && (
                        <Badge variant="secondary">Not assigned</Badge>
                    )}
                </div>
                <CardDescription>
                    {track.passed_modules} of {track.total_modules} modules
                    passed
                </CardDescription>
                <CourseProgressBar
                    value={track.passed_modules}
                    max={track.total_modules}
                />
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto border-t">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Module</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Lessons</TableHead>
                                <TableHead>Best score</TableHead>
                                <TableHead>Attempts</TableHead>
                                <TableHead>Last attempt</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {track.modules.map((module) => {
                                const isOpen = openModule === module.slug;
                                const hasAttempts = module.attempts.length > 0;

                                return (
                                    <Fragment key={module.slug}>
                                        <TableRow
                                            className={
                                                hasAttempts
                                                    ? 'cursor-pointer'
                                                    : undefined
                                            }
                                            onClick={() =>
                                                hasAttempts &&
                                                setOpenModule(
                                                    isOpen ? null : module.slug,
                                                )
                                            }
                                            aria-expanded={
                                                hasAttempts ? isOpen : undefined
                                            }
                                        >
                                            <TableCell className="font-medium">
                                                <span className="inline-flex items-center gap-1">
                                                    {hasAttempts ? (
                                                        isOpen ? (
                                                            <ChevronDown className="size-4" />
                                                        ) : (
                                                            <ChevronRight className="size-4" />
                                                        )
                                                    ) : (
                                                        <span className="inline-block size-4" />
                                                    )}
                                                    {module.title}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <ModuleStatus module={module} />
                                            </TableCell>
                                            <TableCell>
                                                {module.completed_lessons}/
                                                {module.total_lessons}
                                            </TableCell>
                                            <TableCell>
                                                {module.best_score !== null
                                                    ? `${module.best_score}%`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {module.attempts_count}
                                            </TableCell>
                                            <TableCell>
                                                {hasAttempts
                                                    ? formatDate(
                                                          module.attempts[0]
                                                              .created_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                        {isOpen && (
                                            <TableRow className="bg-muted/30 hover:bg-muted/30">
                                                <TableCell colSpan={6}>
                                                    <AttemptHistory
                                                        attempts={
                                                            module.attempts
                                                        }
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
                {track.exam && <ExamPanel userId={userId} exam={track.exam} />}
            </CardContent>
        </Card>
    );
}

function ExamPanel({
    userId,
    exam,
}: {
    userId: number;
    exam: ExamSummary & { id: number };
}) {
    const [pendingSection, setPendingSection] = useState<string | null>(null);

    function grantRetake(section: ExamSectionSummary) {
        router.post(
            ExamRetakeController.store.url(userId),
            { course_exam_id: exam.id, section: section.key },
            {
                preserveScroll: true,
                onStart: () => setPendingSection(section.key),
                onFinish: () => setPendingSection(null),
            },
        );
    }

    return (
        <div className="space-y-3 border-t p-6">
            <div className="flex flex-wrap items-center gap-2">
                <Trophy className="size-4" />
                <span className="font-semibold">{exam.title}</span>
                <ExamStatusBadge status={exam.status} />
            </div>

            {!exam.is_unlocked ? (
                <p className="text-muted-foreground text-sm">
                    Unlocks once every module is passed.
                </p>
            ) : (
                <div className="grid gap-3 md:grid-cols-2">
                    {exam.sections.map((section) => (
                        <div
                            key={section.key}
                            className="space-y-2 rounded-lg border p-3"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-medium">
                                    {section.title}
                                </span>
                                {section.is_passed ? (
                                    <Badge variant="outline" className="gap-1">
                                        <CheckCircle2 className="size-3 text-emerald-600" />
                                        Passed
                                    </Badge>
                                ) : section.is_out_of_attempts ? (
                                    <Badge variant="destructive">
                                        Out of attempts
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        {section.attempts_used > 0
                                            ? 'Not passed yet'
                                            : 'Not started'}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                Best score:{' '}
                                {section.best_score !== null
                                    ? `${section.best_score}%`
                                    : '—'}{' '}
                                · {section.attempts_used}/
                                {section.attempts_allowed} attempts used
                            </p>
                            {section.attempts.length > 0 && (
                                <ul className="space-y-1 text-sm">
                                    {section.attempts.map((attempt, index) => (
                                        <li
                                            key={attempt.id}
                                            className="flex items-center gap-2"
                                        >
                                            <span className="text-muted-foreground w-6">
                                                #
                                                {section.attempts.length -
                                                    index}
                                            </span>
                                            <span className="w-10 font-medium">
                                                {attempt.score_pct}%
                                            </span>
                                            <Badge
                                                variant={
                                                    attempt.passed
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                            >
                                                {attempt.passed
                                                    ? 'Passed'
                                                    : 'Failed'}
                                            </Badge>
                                            <span className="text-muted-foreground">
                                                {formatDate(
                                                    attempt.submitted_at,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {section.is_out_of_attempts && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={pendingSection === section.key}
                                    onClick={() => grantRetake(section)}
                                >
                                    {pendingSection === section.key ? (
                                        <Spinner />
                                    ) : (
                                        <RotateCcw />
                                    )}
                                    Grant retake
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function ModuleStatus({ module }: { module: ModuleProgress }) {
    if (module.is_passed) {
        return (
            <Badge variant="outline" className="gap-1">
                <CheckCircle2 className="size-3 text-emerald-600" />
                Passed
            </Badge>
        );
    }

    if (!module.is_unlocked) {
        return (
            <Badge variant="secondary" className="gap-1">
                <Lock className="size-3" />
                Locked
            </Badge>
        );
    }

    if (module.attempts_count > 0) {
        return <Badge variant="destructive">Not passed yet</Badge>;
    }

    return (
        <Badge variant="secondary">
            {module.completed_lessons > 0 ? 'In progress' : 'Not started'}
        </Badge>
    );
}

function AttemptHistory({ attempts }: { attempts: QuizAttempt[] }) {
    return (
        <div className="space-y-2 py-1 pl-5">
            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                Quiz attempts
            </p>
            <ul className="space-y-1">
                {attempts.map((attempt, index) => (
                    <li
                        key={attempt.id}
                        className="flex flex-wrap items-center gap-3 text-sm"
                    >
                        <span className="text-muted-foreground w-20">
                            #{attempts.length - index}
                        </span>
                        <span className="w-12 font-medium">
                            {attempt.score_pct}%
                        </span>
                        <Badge
                            variant={attempt.passed ? 'outline' : 'destructive'}
                        >
                            {attempt.passed ? 'Passed' : 'Failed'}
                        </Badge>
                        <span className="text-muted-foreground">
                            {formatDate(attempt.created_at)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function TrackToggles({
    userId,
    availableTracks,
}: {
    userId: number;
    availableTracks: Props['availableTracks'];
}) {
    const [pendingTrackId, setPendingTrackId] = useState<number | null>(null);

    function toggle(trackId: number, checked: boolean) {
        const options = {
            preserveScroll: true,
            onStart: () => setPendingTrackId(trackId),
            onFinish: () => setPendingTrackId(null),
        };

        if (checked) {
            router.post(
                UserTrackController.store.url([userId, trackId]),
                {},
                options,
            );
        } else {
            router.delete(
                UserTrackController.destroy.url([userId, trackId]),
                options,
            );
        }
    }

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Training tracks</CardTitle>
                <CardDescription>
                    Toggle a track on to show it under this user's My Training.
                    Turning it off keeps their progress.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {availableTracks.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No training tracks have been imported yet.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {availableTracks.map((track) => (
                            <li
                                key={track.id}
                                className="flex items-center gap-3 rounded-lg border p-3"
                            >
                                <Checkbox
                                    id={`track-${track.id}`}
                                    checked={track.is_assigned}
                                    disabled={pendingTrackId === track.id}
                                    onCheckedChange={(checked) =>
                                        toggle(track.id, checked === true)
                                    }
                                />
                                <label
                                    htmlFor={`track-${track.id}`}
                                    className="min-w-0 flex-1 cursor-pointer truncate text-sm font-medium"
                                >
                                    {track.name}
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
