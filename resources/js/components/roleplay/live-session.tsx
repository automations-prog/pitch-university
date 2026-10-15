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
import { useEffect, useState } from 'react';
import { CallResult } from '@/components/roleplay/call-result';
import { CallScreen, type CallView } from '@/components/roleplay/call-screen';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    useRoleplayRealtimeCall,
    type RoleplayCallPhase,
    type TransferStage,
} from '@/hooks/use-roleplay-realtime-call';
import { brandButtonClass } from '@/lib/brand-theme';
import {
    useRoleplayContent,
    useRoleplayVertical,
} from '@/lib/roleplay-content';
import {
    TRANSFER_ASK_SECTION,
    type PublicPersona,
    type RoleplayVertical,
} from '@/lib/roleplay-data';

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
 * A training module's short scene: where on the script it starts, how long
 * it may run, and whether it includes the transfer and the coding.
 */
export type RoleplayScene = {
    context: string;
    startSection: string;
    maxSeconds: number;
    transfer: boolean;
    codeCall: boolean;
};

/**
 * A live call against the AI consumer, from dial to graded disposition.
 * Keyed by session so every retry starts with a fresh call. With a scene,
 * it's a short stretch of a call that ends on time.
 */
export function LiveRoleplaySession({
    persona,
    agentName,
    scene,
    onRetry,
    onChangeLevel,
    changeLevelLabel,
}: {
    persona: PublicPersona;
    agentName: string;
    scene?: RoleplayScene;
    onRetry: () => void;
    onChangeLevel: () => void;
    changeLevelLabel?: string;
}) {
    const { levels, scriptSections } = useRoleplayContent();
    const vertical = useRoleplayVertical();
    const call = useRoleplayRealtimeCall({
        sessionId: persona.id,
        startingPatience: persona.patience,
        vertical,
    });
    const [scriptStep, setScriptStep] = useState(() =>
        scene
            ? Math.max(
                  scriptSections.findIndex(
                      (section) => section.id === scene.startSection,
                  ),
                  0,
              )
            : 0,
    );
    const transferAskStep = scriptSections.findIndex(
        (section) => section.id === TRANSFER_ASK_SECTION,
    );
    const isOnCall = call.phase === 'active' || call.phase === 'transferring';
    const secondsLeft = scene
        ? Math.max(scene.maxSeconds - call.elapsedSeconds, 0)
        : null;
    const { endCall } = call;

    // A scene ends itself when its time is up.
    useEffect(() => {
        if (isOnCall && secondsLeft === 0) {
            endCall();
        }
    }, [isOnCall, secondsLeft, endCall]);

    if (call.phase === 'ended' && call.endReason) {
        return (
            <CallResult
                endReason={call.endReason}
                requiresDisposition={scene?.codeCall ?? true}
                onComplete={call.complete}
                onRetry={onRetry}
                onChangeLevel={onChangeLevel}
                changeLevelLabel={changeLevelLabel}
            />
        );
    }

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
            hideRebuttals={scene !== undefined}
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
                            {scene ? 'End scene' : 'End call'}
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
                        vertical={vertical}
                        scene={scene}
                        secondsLeft={secondsLeft}
                        phase={call.phase}
                        transferStage={call.transferStage}
                        error={call.error}
                        leadName={persona.lead.name}
                        canTransfer={scriptStep >= transferAskStep}
                        onDial={() => void call.start()}
                        onTransfer={call.transfer}
                        onCompleteTransfer={call.completeTransfer}
                        onEndScene={call.endCall}
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
    vertical,
    scene,
    secondsLeft,
    phase,
    transferStage,
    error,
    leadName,
    canTransfer,
    onDial,
    onTransfer,
    onCompleteTransfer,
    onEndScene,
    onRetry,
}: {
    vertical: RoleplayVertical;
    scene?: RoleplayScene;
    secondsLeft: number | null;
    phase: RoleplayCallPhase;
    transferStage: TransferStage | null;
    error: string | null;
    leadName: string;
    canTransfer: boolean;
    onDial: () => void;
    onTransfer: () => void;
    onCompleteTransfer: () => void;
    onEndScene: () => void;
    onRetry: () => void;
}) {
    const row = 'flex flex-wrap items-center justify-between gap-3';
    const { handoff, handoffParty, handoffIntro } = vertical;
    const timeLeft =
        secondsLeft === null
            ? ''
            : ` ${Math.floor(secondsLeft / 60)}:${String(secondsLeft % 60).padStart(2, '0')} left.`;

    switch (phase) {
        case 'idle':
            return (
                <div className={row}>
                    <p className="text-muted-foreground text-sm">
                        {scene
                            ? `${scene.context} Your browser will ask for the microphone; use headphones.`
                            : "Your browser will ask for the microphone. You read the script; the consumer answers. Use headphones so the consumer doesn't hear their own echo."}
                    </p>
                    <Button className={brandButtonClass} onClick={onDial}>
                        <PhoneCall />
                        {scene ? 'Start the scene' : `Dial ${leadName}`}
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
                            ? `It's ringing. Fluff away and keep them talking until the ${handoffParty} picks up.`
                            : handoffIntro
                              ? `Introduce them: "${handoffIntro}" Leave once you hear both of them speaking.`
                              : 'Stay quiet. Complete the transfer once you have heard both parties speak.'}
                    </p>
                    <Button
                        className={brandButtonClass}
                        onClick={onCompleteTransfer}
                    >
                        <PhoneOutgoing />
                        {handoff === 'warm'
                            ? 'Leave conference'
                            : 'Complete transfer'}
                    </Button>
                </div>
            );
        default:
            if (scene && !scene.transfer) {
                return (
                    <div className={row}>
                        <p className="text-muted-foreground text-sm">
                            {scene.context}
                            {timeLeft}
                        </p>
                        <Button
                            className={brandButtonClass}
                            onClick={onEndScene}
                        >
                            <PhoneOff />
                            End scene
                        </Button>
                    </div>
                );
            }

            return (
                <div className={row}>
                    <p className="text-muted-foreground text-sm">
                        {canTransfer
                            ? `Ask for the transfer, then stop and wait for ${handoff === 'warm' ? 'an okay' : 'a yes'} before you click.`
                            : `Tap Next on the script as you go. ${handoff === 'warm' ? 'Conference' : 'Transfer'} unlocks at the transfer ask.`}
                        {timeLeft}
                    </p>
                    <Button
                        className={brandButtonClass}
                        disabled={!canTransfer}
                        onClick={onTransfer}
                    >
                        <PhoneForwarded />
                        {handoff === 'warm' ? 'Conference' : 'Transfer'}
                    </Button>
                </div>
            );
    }
}
