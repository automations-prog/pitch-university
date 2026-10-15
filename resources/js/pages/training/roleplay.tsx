import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronRight,
    Headphones,
    PhoneCall,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import { LiveRoleplaySession } from '@/components/roleplay/live-session';
import { RoleplayStatusBadge } from '@/components/roleplay-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass } from '@/lib/brand-theme';
import { xsrfHeader } from '@/lib/csrf';
import {
    RoleplayContentContext,
    RoleplayVerticalContext,
} from '@/lib/roleplay-content';
import type {
    PublicPersona,
    RoleplayContent,
    RoleplaySessionResult,
    RoleplayVertical,
} from '@/lib/roleplay-data';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import type {
    CourseModule,
    CourseModuleProgress,
    CourseModuleRoleplay,
    CourseTrack,
} from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    progress: CourseModuleProgress;
    roleplay: CourseModuleRoleplay;
    vertical: RoleplayVertical;
    content: RoleplayContent;
    recentSessions: RoleplaySessionResult[];
    nextModule: { slug: string; title: string } | null;
};

export default function TrainingRoleplay({
    track,
    module,
    progress,
    roleplay,
    vertical,
    content,
    recentSessions,
    nextModule,
}: Props) {
    const { auth } = usePage().props;

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
                title: 'Roleplay',
                href: TrainingController.showRoleplay([
                    track.slug,
                    module.slug,
                ]),
            },
        ],
    });

    const agentName = auth.user.name.split(' ')[0];
    const [persona, setPersona] = useState<PublicPersona | null>(null);
    const [sessionKey, setSessionKey] = useState(0);
    const [isStarting, setIsStarting] = useState(false);
    const [startError, setStartError] = useState<string | null>(null);
    const status = progress.roleplay?.status ?? 'not_started';

    // The scene's script panel only shows the steps the scene covers.
    const sceneStart = content.scriptSections.findIndex(
        (section) => section.id === roleplay.start_section,
    );
    const sceneEnd = content.scriptSections.findIndex(
        (section) => section.id === roleplay.end_section,
    );
    const sceneContent: RoleplayContent = {
        ...content,
        scriptSections: content.scriptSections.slice(
            Math.max(sceneStart, 0),
            sceneEnd === -1 ? undefined : sceneEnd + 1,
        ),
    };

    const startCall = async () => {
        setIsStarting(true);
        setStartError(null);

        try {
            const response = await fetch(
                TrainingController.startRoleplay.url([track.slug, module.slug]),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        ...xsrfHeader(),
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(
                    response.status === 429
                        ? "You've reached today's roleplay call limit. Try again tomorrow."
                        : `Could not start a call (HTTP ${response.status}).`,
                );
            }

            setPersona((await response.json()) as PublicPersona);
            setSessionKey((key) => key + 1);
        } catch (caught) {
            setPersona(null);
            setStartError(
                caught instanceof Error
                    ? caught.message
                    : 'Could not start a call.',
            );
        } finally {
            setIsStarting(false);
        }
    };

    /** Back to the briefing, with the latest pass status and calls. */
    const backToBriefing = () => {
        setPersona(null);
        router.reload({ only: ['progress', 'recentSessions'] });
    };

    return (
        <RoleplayVerticalContext value={vertical}>
            <RoleplayContentContext value={content}>
                <Head title={`${roleplay.title} · ${module.title}`} />

                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="space-y-2">
                        <Link
                            href={TrainingController.showModule([
                                track.slug,
                                module.slug,
                            ])}
                            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                        >
                            <ArrowLeft className="size-4" />
                            Back to {module.title}
                        </Link>
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-semibold tracking-tight">
                                Roleplay: {roleplay.title}
                            </h2>
                            <RoleplayStatusBadge status={status} />
                        </div>
                        <p className="text-muted-foreground max-w-3xl text-sm">
                            A short scene of about a minute from a live call.
                            Pass it to finish {module.title}. You can try as
                            many times as you need, and every attempt is a new
                            consumer.
                        </p>
                    </div>

                    {persona ? (
                        <RoleplayContentContext value={sceneContent}>
                            <LiveRoleplaySession
                                key={sessionKey}
                                persona={persona}
                                agentName={agentName}
                                scene={{
                                    context: roleplay.context,
                                    startSection: roleplay.start_section,
                                    maxSeconds: roleplay.max_seconds,
                                    transfer: roleplay.transfer,
                                    codeCall: roleplay.code_call,
                                }}
                                onRetry={() => void startCall()}
                                onChangeLevel={backToBriefing}
                                changeLevelLabel="Back to briefing"
                            />
                        </RoleplayContentContext>
                    ) : (
                        <div className="grid gap-6 lg:grid-cols-3">
                            <Card className="lg:col-span-2">
                                <CardHeader>
                                    <CardTitle>Briefing</CardTitle>
                                    <CardDescription>
                                        About{' '}
                                        {Math.ceil(roleplay.max_seconds / 60)}{' '}
                                        {roleplay.max_seconds > 60
                                            ? 'minutes'
                                            : 'minute'}{' '}
                                        · Level {roleplay.level} consumer
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm">
                                    <p>{roleplay.briefing}</p>
                                    <div>
                                        <p className="font-semibold">
                                            To pass, you must
                                        </p>
                                        <ul className="mt-2 flex flex-col gap-1.5">
                                            {[
                                                ...roleplay.pass_criteria,
                                                ...(roleplay.code_call
                                                    ? [
                                                          'Code the call correctly',
                                                      ]
                                                    : []),
                                                "Keep them on the line (they don't hang up on you)",
                                            ]
                                                .filter(
                                                    (label, index, labels) =>
                                                        labels.indexOf(
                                                            label,
                                                        ) === index,
                                                )
                                                .map((label) => (
                                                    <li
                                                        key={label}
                                                        className="flex items-start gap-2"
                                                    >
                                                        <CheckCircle2 className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                                        {label}
                                                    </li>
                                                ))}
                                        </ul>
                                    </div>
                                    <p className="text-muted-foreground flex items-center gap-2">
                                        <Headphones className="size-4 shrink-0" />
                                        Use headphones and a quiet room. Your
                                        browser will ask for the microphone.
                                    </p>
                                </CardContent>
                            </Card>

                            <div className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Your progress</CardTitle>
                                        <CardDescription>
                                            {progress.roleplay
                                                ?.attempts_count ?? 0}{' '}
                                            {progress.roleplay
                                                ?.attempts_count === 1
                                                ? 'attempt'
                                                : 'attempts'}{' '}
                                            so far
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {startError && (
                                            <p className="text-destructive flex items-center gap-2 text-sm">
                                                <TriangleAlert className="size-4 shrink-0" />
                                                {startError}
                                            </p>
                                        )}
                                        <Button
                                            className={`w-full ${progress.is_passed ? '' : brandButtonClass}`}
                                            variant={
                                                progress.is_passed
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            disabled={isStarting}
                                            onClick={() => void startCall()}
                                        >
                                            {isStarting ? (
                                                <Spinner />
                                            ) : (
                                                <PhoneCall />
                                            )}
                                            {progress.is_passed
                                                ? 'Practice again'
                                                : status === 'failed'
                                                  ? 'Try again'
                                                  : 'Start the scene'}
                                        </Button>
                                        {progress.is_passed && (
                                            <Button
                                                asChild
                                                className={`w-full ${brandButtonClass}`}
                                            >
                                                <Link
                                                    href={
                                                        nextModule
                                                            ? TrainingController.showModule(
                                                                  [
                                                                      track.slug,
                                                                      nextModule.slug,
                                                                  ],
                                                              )
                                                            : TrainingController.showTrack(
                                                                  track.slug,
                                                              )
                                                    }
                                                >
                                                    {nextModule
                                                        ? `Next: ${nextModule.title}`
                                                        : `Back to ${track.name}`}
                                                    <ChevronRight />
                                                </Link>
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>

                                <RecentCalls
                                    sessions={recentSessions}
                                    track={track}
                                    module={module}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </RoleplayContentContext>
        </RoleplayVerticalContext>
    );
}

function RecentCalls({
    sessions,
    track,
    module,
}: {
    sessions: RoleplaySessionResult[];
    track: CourseTrack;
    module: CourseModule;
}) {
    if (sessions.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your attempts</CardTitle>
                <CardDescription>
                    Open one to see its score, transcript and recording.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col divide-y">
                {sessions.map((session) => (
                    <Link
                        key={session.id}
                        href={TrainingController.showRoleplayAttempt([
                            track.slug,
                            module.slug,
                            session.id,
                        ])}
                        className="hover:bg-muted/50 -mx-2 flex items-center justify-between gap-2 rounded-md px-2 py-2 text-sm"
                    >
                        <div className="min-w-0">
                            <p className="font-medium">{session.lead.name}</p>
                            {session.ended_at && (
                                <p className="text-muted-foreground text-xs">
                                    {new Date(
                                        session.ended_at,
                                    ).toLocaleString()}
                                </p>
                            )}
                        </div>
                        <Badge
                            variant={
                                session.passed === false
                                    ? 'destructive'
                                    : session.passed
                                      ? 'default'
                                      : 'secondary'
                            }
                        >
                            {session.passed === null
                                ? 'Grading'
                                : session.passed
                                  ? 'Passed'
                                  : 'Failed'}
                        </Badge>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}
