import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Mic,
    MicOff,
    PhoneCall,
    PhoneForwarded,
    PhoneOff,
    PhoneOutgoing,
    TriangleAlert,
    Volume2,
} from 'lucide-react';
import { useState } from 'react';
import { CallResult } from '@/components/roleplay/call-result';
import {
    CallScreen,
    useElapsedSeconds,
    type CallView,
} from '@/components/roleplay/call-screen';
import { LevelPicker } from '@/components/roleplay/level-picker';
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
import { Spinner } from '@/components/ui/spinner';
import { useRoleplayCall } from '@/hooks/use-roleplay-call';
import {
    useRoleplayRealtimeCall,
    type RoleplayCallPhase,
    type TransferStage,
} from '@/hooks/use-roleplay-realtime-call';
import { brandButtonClass } from '@/lib/brand-theme';
import { xsrfHeader } from '@/lib/csrf';
import {
    RoleplayContentContext,
    useRoleplayContent,
} from '@/lib/roleplay-content';
import {
    OUTCOME_LABELS,
    TRANSFER_ASK_SECTION,
    type DifficultyLevel,
    type PublicPersona,
    type RoleplayContent,
    type RoleplaySessionResult,
} from '@/lib/roleplay-data';
import { generatePersona, type Persona } from '@/lib/roleplay-persona';
import { dashboard } from '@/routes';
import { index as roleplayIndex } from '@/routes/roleplay';
import {
    show as showSession,
    store as storeSession,
} from '@/routes/roleplay/sessions';

type ActiveSession =
    | { key: number; kind: 'live'; persona: PublicPersona }
    | { key: number; kind: 'mock'; persona: Persona };

export default function RoleplayIndex({
    content,
    recentSessions,
}: {
    content: RoleplayContent;
    recentSessions: RoleplaySessionResult[];
}) {
    const { auth } = usePage().props;
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
            const response = await fetch(storeSession.url(), {
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
        <RoleplayContentContext value={content}>
            <Head title="Roleplay" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Roleplay
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {session
                            ? 'Follow the script, rebut every objection, and code the call correctly.'
                            : 'Pick a difficulty and practice a Medicare call against an AI consumer.'}
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
                            onPracticeWithoutMicChange={setPracticeWithoutMic}
                        />
                        <RecentCalls sessions={recentSessions} />
                    </>
                )}
            </div>
        </RoleplayContentContext>
    );
}

const PHASE_LABELS: Record<RoleplayCallPhase, string> = {
    idle: 'Ready to dial',
    connecting: 'Connecting',
    ringing: 'Ringing',
    active: 'On call',
    transferring: 'Transferring',
    ended: 'Call ended',
    error: 'Call failed',
};

/**
 * A live call against the AI consumer, from dial to graded disposition.
 * Keyed by session so every retry starts with a fresh call.
 */
function LiveRoleplaySession({
    persona,
    agentName,
    onRetry,
    onChangeLevel,
}: {
    persona: PublicPersona;
    agentName: string;
    onRetry: () => void;
    onChangeLevel: () => void;
}) {
    const { levels, scriptSections } = useRoleplayContent();
    const call = useRoleplayRealtimeCall({
        sessionId: persona.id,
        level: persona.level,
        startingPatience: persona.patience,
    });
    const [scriptStep, setScriptStep] = useState(0);
    const transferAskStep = scriptSections.findIndex(
        (section) => section.id === TRANSFER_ASK_SECTION,
    );

    if (call.phase === 'ended' && call.endReason) {
        return (
            <CallResult
                endReason={call.endReason}
                onComplete={call.complete}
                onRetry={onRetry}
                onChangeLevel={onChangeLevel}
            />
        );
    }

    const isOnCall = call.phase === 'active' || call.phase === 'transferring';
    const view: CallView = {
        lead: persona.lead,
        level: levels.find((level) => level.level === persona.level)!,
        quirk: persona.quirk,
        patience: call.patience,
        maxPatience: persona.patience,
        elapsedSeconds: call.elapsedSeconds,
        statusLabel: PHASE_LABELS[call.phase],
        isOver: call.phase === 'error',
        isObjectionOpen: call.openObjections.length > 0,
        transcript: call.transcript,
        scriptStep,
    };

    return (
        <CallScreen
            view={view}
            agentName={agentName}
            conversationNote={
                call.phase === 'connecting'
                    ? 'Connecting…'
                    : call.phase === 'ringing'
                      ? 'Ringing…'
                      : 'Live call · AI consumer'
            }
            onScriptStepChange={isOnCall ? setScriptStep : undefined}
            barActions={
                isOnCall && (
                    <div className="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            size="icon"
                            aria-label={call.muted ? 'Unmute' : 'Mute'}
                            onClick={call.toggleMute}
                        >
                            {call.muted ? <MicOff /> : <Mic />}
                        </Button>
                        <Button
                            variant="destructive"
                            className="font-bold"
                            onClick={call.endCall}
                        >
                            <PhoneOff />
                            End call
                        </Button>
                    </div>
                )
            }
            footer={
                <div className="flex flex-col gap-3">
                    {call.audioBlocked && (
                        <AudioBlockedNotice onEnable={call.enableAudio} />
                    )}
                    <LiveFooter
                        phase={call.phase}
                        transferStage={call.transferStage}
                        error={call.error}
                        leadName={persona.lead.name}
                        canTransfer={scriptStep >= transferAskStep}
                        onDial={() => void call.start()}
                        onTransfer={call.transfer}
                        onCompleteTransfer={call.completeTransfer}
                        onRetry={onRetry}
                    />
                </div>
            }
        />
    );
}

/**
 * The browser blocked the consumer's audio. A click is allowed to start it,
 * and the consumer only says hello once it plays.
 */
function AudioBlockedNotice({ onEnable }: { onEnable: () => void }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2">
            <p className="flex items-center gap-2 text-sm">
                <TriangleAlert className="size-4 shrink-0 text-amber-600" />
                Your browser blocked the customer's audio.
            </p>
            <Button size="sm" className={brandButtonClass} onClick={onEnable}>
                <Volume2 />
                Turn on audio
            </Button>
        </div>
    );
}

function LiveFooter({
    phase,
    transferStage,
    error,
    leadName,
    canTransfer,
    onDial,
    onTransfer,
    onCompleteTransfer,
    onRetry,
}: {
    phase: RoleplayCallPhase;
    transferStage: TransferStage | null;
    error: string | null;
    leadName: string;
    canTransfer: boolean;
    onDial: () => void;
    onTransfer: () => void;
    onCompleteTransfer: () => void;
    onRetry: () => void;
}) {
    const row = 'flex flex-wrap items-center justify-between gap-3';

    switch (phase) {
        case 'idle':
            return (
                <div className={row}>
                    <p className="text-muted-foreground text-sm">
                        Your browser will ask for the microphone. You read the
                        script; the consumer answers. Use headphones so the
                        consumer doesn't hear their own echo.
                    </p>
                    <Button className={brandButtonClass} onClick={onDial}>
                        <PhoneCall />
                        Dial {leadName}
                    </Button>
                </div>
            );
        case 'connecting':
            return (
                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                    <Spinner />
                    Connecting the call…
                </p>
            );
        case 'ringing':
            return (
                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                    <Spinner />
                    Ringing {leadName}… wait for them to say hello before you
                    start.
                </p>
            );
        case 'error':
            return (
                <div className={row}>
                    <p className="text-destructive flex items-center gap-2 text-sm">
                        <TriangleAlert className="size-4 shrink-0" />
                        {error ?? 'The call failed.'}
                    </p>
                    <Button className={brandButtonClass} onClick={onRetry}>
                        Start a new call
                        <ChevronRight />
                    </Button>
                </div>
            );
        case 'transferring':
            return (
                <div className={row}>
                    <p className="text-muted-foreground text-sm">
                        {transferStage === 'ringing'
                            ? "It's ringing. Fluff away and keep them talking until the specialist picks up."
                            : 'Stay quiet. Complete the transfer once you have heard both parties speak.'}
                    </p>
                    <Button
                        className={brandButtonClass}
                        onClick={onCompleteTransfer}
                    >
                        <PhoneOutgoing />
                        Complete transfer
                    </Button>
                </div>
            );
        default:
            return (
                <div className={row}>
                    <p className="text-muted-foreground text-sm">
                        {canTransfer
                            ? 'Ask for the transfer, then stop and wait for a yes before you click.'
                            : 'Tap Next on the script as you go. Transfer unlocks at the transfer ask.'}
                    </p>
                    <Button
                        className={brandButtonClass}
                        disabled={!canTransfer}
                        onClick={onTransfer}
                    >
                        <PhoneForwarded />
                        Transfer
                    </Button>
                </div>
            );
    }
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
                            Script complete. Stay quiet while the agent
                            connects.
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
                        href={showSession(session.id)}
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

RoleplayIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roleplay', href: roleplayIndex() },
    ],
};
