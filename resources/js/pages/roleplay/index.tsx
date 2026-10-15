import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ChevronRight, PhoneOff, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import {
    CallScreen,
    useElapsedSeconds,
    type CallView,
} from '@/components/roleplay/call-screen';
import { LevelPicker } from '@/components/roleplay/level-picker';
import { LiveRoleplaySession } from '@/components/roleplay/live-session';
import { WrapUp } from '@/components/roleplay/wrap-up';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useRoleplayCall } from '@/hooks/use-roleplay-call';
import { brandButtonClass } from '@/lib/brand-theme';
import { xsrfHeader } from '@/lib/csrf';
import {
    RoleplayContentContext,
    RoleplayVerticalContext,
    useRoleplayContent,
    useRoleplayVertical,
} from '@/lib/roleplay-content';
import {
    OUTCOME_LABELS,
    type DifficultyLevel,
    type PublicPersona,
    type RoleplayContent,
    type RoleplaySessionResult,
    type RoleplayVertical,
} from '@/lib/roleplay-data';
import { generatePersona, type Persona } from '@/lib/roleplay-persona';
import { roleplayRoutes } from '@/lib/roleplay-routes';
import { dashboard } from '@/routes';

type ActiveSession =
    | { key: number; kind: 'live'; persona: PublicPersona }
    | { key: number; kind: 'mock'; persona: Persona };

export default function RoleplayIndex({
    vertical,
    content,
    recentSessions,
}: {
    vertical: RoleplayVertical;
    content: RoleplayContent;
    recentSessions: RoleplaySessionResult[];
}) {
    const { auth } = usePage().props;
    const routes = roleplayRoutes(vertical.id);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: `${vertical.label} Roleplay`, href: routes.index() },
        ],
    });

    const agentName = auth.user.name.split(' ')[0];
    const [selectedLevel, setSelectedLevel] = useState<DifficultyLevel>(1);
    const [practiceWithoutMic, setPracticeWithoutMic] = useState(false);
    const [isStarting, setIsStarting] = useState(false);
    const [startError, setStartError] = useState<string | null>(null);
    const [session, setSession] = useState<ActiveSession | null>(null);

    /**
     * Live calls get their persona from the server, which keeps the
     * outcome and DQ trap to itself. The mock builds one in the browser.
     */
    const startSession = async (level: DifficultyLevel) => {
        const key = (session?.key ?? 0) + 1;

        if (practiceWithoutMic) {
            setSession({
                key,
                kind: 'mock',
                persona: generatePersona(content, level),
            });

            return;
        }

        setIsStarting(true);
        setStartError(null);

        try {
            const response = await fetch(routes.sessions.store.url(), {
                method: 'POST',
                body: JSON.stringify({ level }),
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...xsrfHeader(),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(
                    response.status === 429
                        ? "You've reached today's roleplay call limit. Try again tomorrow, or practice without mic."
                        : `Could not start a call (HTTP ${response.status}).`,
                );
            }

            setSession({
                key,
                kind: 'live',
                persona: (await response.json()) as PublicPersona,
            });
        } catch (caught) {
            setSession(null);
            setStartError(
                caught instanceof Error
                    ? caught.message
                    : 'Could not start a call.',
            );
        } finally {
            setIsStarting(false);
        }
    };

    const retry = (level: DifficultyLevel) => void startSession(level);
    const changeLevel = () => setSession(null);

    return (
        <RoleplayVerticalContext value={vertical}>
            <RoleplayContentContext value={content}>
                <Head title={`${vertical.label} Roleplay`} />

                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Roleplay
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {session
                                ? 'Follow the script, rebut every objection, and code the call correctly.'
                                : `Pick a difficulty and practice ${vertical.id === 'aca' ? 'an' : 'a'} ${vertical.label} call against an AI consumer.`}
                        </p>
                    </div>

                    {session?.kind === 'live' && (
                        <LiveRoleplaySession
                            key={session.key}
                            persona={session.persona}
                            agentName={agentName}
                            onRetry={() => retry(session.persona.level)}
                            onChangeLevel={changeLevel}
                        />
                    )}

                    {session?.kind === 'mock' && (
                        <MockRoleplaySession
                            key={session.key}
                            persona={session.persona}
                            agentName={agentName}
                            onRetry={() => retry(session.persona.level.level)}
                            onChangeLevel={changeLevel}
                        />
                    )}

                    {!session && (
                        <>
                            <LevelPicker
                                selectedLevel={selectedLevel}
                                onSelect={setSelectedLevel}
                                onStart={() => void startSession(selectedLevel)}
                                isStarting={isStarting}
                                error={startError}
                                practiceWithoutMic={practiceWithoutMic}
                                onPracticeWithoutMicChange={
                                    setPracticeWithoutMic
                                }
                            />
                            <RecentCalls sessions={recentSessions} />
                        </>
                    )}
                </div>
            </RoleplayContentContext>
        </RoleplayVerticalContext>
    );
}

/**
 * Practice without mic: the click-through mock, with the trainee reading
 * steps and rebuttals by button.
 */
function MockRoleplaySession({
    persona,
    agentName,
    onRetry,
    onChangeLevel,
}: {
    persona: Persona;
    agentName: string;
    onRetry: () => void;
    onChangeLevel: () => void;
}) {
    const { scriptSections } = useRoleplayContent();
    const vertical = useRoleplayVertical();
    const call = useRoleplayCall(persona, agentName, scriptSections);
    const [isWrappingUp, setIsWrappingUp] = useState(false);
    const isOver = call.endReason !== null;
    const elapsedSeconds = useElapsedSeconds(!isOver);

    if (isWrappingUp && call.endReason) {
        return (
            <WrapUp
                persona={persona}
                endReason={call.endReason}
                stepsRead={call.currentStep}
                facedObjections={call.facedObjections}
                onRetry={onRetry}
                onChangeLevel={onChangeLevel}
            />
        );
    }

    const isObjectionOpen = call.openObjections.length > 0 && !isOver;
    const nextSection = scriptSections[call.currentStep];
    const endAndWrapUp = () => {
        call.endCall();
        setIsWrappingUp(true);
    };
    const wrapUpButton = (
        <Button
            className={brandButtonClass}
            onClick={() => setIsWrappingUp(true)}
        >
            Wrap up
            <ChevronRight />
        </Button>
    );
    const row = 'flex flex-wrap items-center justify-between gap-3';

    const view: CallView = {
        lead: persona.lead,
        level: persona.level,
        quirk: persona.quirk,
        patience: call.patience,
        maxPatience: persona.patience,
        elapsedSeconds,
        statusLabel: isOver ? 'Call ended' : 'On call',
        isOver,
        isObjectionOpen,
        transcript: call.transcript,
        scriptStep: call.currentStep,
    };

    return (
        <CallScreen
            view={view}
            agentName={agentName}
            conversationNote="Practice without mic"
            onReadRebuttal={call.readRebuttal}
            barActions={
                isOver ? (
                    wrapUpButton
                ) : (
                    <Button
                        variant="destructive"
                        className="font-bold"
                        onClick={endAndWrapUp}
                    >
                        <PhoneOff />
                        End call
                    </Button>
                )
            }
            footer={
                isOver ? (
                    <div className={row}>
                        <p className="text-muted-foreground text-sm">
                            {call.endReason === 'hung_up'
                                ? 'The consumer hung up.'
                                : 'You ended the call.'}
                        </p>
                        {wrapUpButton}
                    </div>
                ) : !nextSection ? (
                    <div className={row}>
                        <p className="text-muted-foreground text-sm">
                            {vertical.handoff === 'warm'
                                ? `Script complete. Introduce them to the ${vertical.handoffParty}, then leave.`
                                : 'Script complete. Stay quiet while the agent connects.'}
                        </p>
                        <Button
                            className={brandButtonClass}
                            onClick={endAndWrapUp}
                        >
                            End & code the call
                            <ChevronRight />
                        </Button>
                    </div>
                ) : (
                    <div className={row}>
                        {isObjectionOpen ? (
                            <p className="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                                <TriangleAlert className="size-4 shrink-0" />
                                Rebut the objection first. Moving on costs
                                patience.
                            </p>
                        ) : (
                            <p className="text-sm">
                                <span className="text-muted-foreground">
                                    Your turn:
                                </span>{' '}
                                <span className="font-medium">
                                    {nextSection.title}
                                </span>
                            </p>
                        )}
                        <Button
                            className={
                                isObjectionOpen ? undefined : brandButtonClass
                            }
                            variant={isObjectionOpen ? 'outline' : 'default'}
                            onClick={call.readStep}
                        >
                            Read step {call.currentStep + 1}
                            <ChevronRight />
                        </Button>
                    </div>
                )
            }
        />
    );
}

function RecentCalls({ sessions }: { sessions: RoleplaySessionResult[] }) {
    const vertical = useRoleplayVertical();

    if (sessions.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Recent calls</CardTitle>
                <CardDescription>
                    Your last {sessions.length} graded calls. Open one to see
                    its full score, transcript and recording.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col divide-y">
                {sessions.map((session) => (
                    <Link
                        key={session.id}
                        href={roleplayRoutes(vertical.id).sessions.show(
                            session.id,
                        )}
                        className="hover:bg-muted/50 -mx-2 flex flex-wrap items-center justify-between gap-2 rounded-md px-2 py-2 text-sm"
                    >
                        <div className="min-w-0">
                            <p className="font-medium">{session.lead.name}</p>
                            <p className="text-muted-foreground text-xs">
                                Level {session.level}
                                {session.expected_outcome &&
                                    ` · should be ${OUTCOME_LABELS[session.expected_outcome]}`}
                                {session.ended_at &&
                                    ` · ${new Date(session.ended_at).toLocaleString()}`}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {session.delivery?.overall != null && (
                                <span className="text-muted-foreground text-xs">
                                    Delivery{' '}
                                    {session.delivery.overall.toFixed(1)}/5
                                </span>
                            )}
                            <Badge
                                variant={
                                    session.passed ? 'default' : 'destructive'
                                }
                            >
                                {session.passed ? 'Passed' : 'Failed'}
                            </Badge>
                        </div>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}
