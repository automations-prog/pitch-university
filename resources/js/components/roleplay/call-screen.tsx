import {
    ChevronRight,
    Heart,
    PhoneOff,
    Sparkles,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { RebuttalsPanel } from '@/components/roleplay/rebuttals-panel';
import { ScriptTracker } from '@/components/roleplay/script-tracker';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type {
    TranscriptLine,
    useRoleplayCall,
} from '@/hooks/use-roleplay-call';
import {
    brandButtonClass,
    brandGradientClass,
    resourceCardClass,
} from '@/lib/brand-theme';
import { SCRIPT_SECTIONS } from '@/lib/roleplay-data';
import type { Persona } from '@/lib/roleplay-persona';
import { cn } from '@/lib/utils';

type RoleplayCall = ReturnType<typeof useRoleplayCall>;

function initialsOf(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function formatDuration(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

export function CallScreen({
    persona,
    agentName,
    call,
    onWrapUp,
}: {
    persona: Persona;
    agentName: string;
    call: RoleplayCall;
    onWrapUp: () => void;
}) {
    const isOver = call.endReason !== null;
    const isObjectionOpen = call.openObjections.length > 0 && !isOver;

    const endAndWrapUp = () => {
        call.endCall();
        onWrapUp();
    };

    return (
        <div className="flex flex-col gap-4">
            <CallBar
                persona={persona}
                patience={call.patience}
                currentStep={call.currentStep}
                isOver={isOver}
                onEnd={endAndWrapUp}
                onWrapUp={onWrapUp}
            />

            <div className="grid items-start gap-4 lg:grid-cols-[20rem_1fr_22rem]">
                <ScriptTracker
                    currentStep={call.currentStep}
                    lead={persona.lead}
                    agentName={agentName}
                />

                <Conversation
                    persona={persona}
                    agentName={agentName}
                    call={call}
                    isObjectionOpen={isObjectionOpen}
                    onEnd={endAndWrapUp}
                    onWrapUp={onWrapUp}
                />

                <RebuttalsPanel
                    disabled={isOver}
                    isObjectionOpen={isObjectionOpen}
                    onRead={call.readRebuttal}
                />
            </div>
        </div>
    );
}

function CallBar({
    persona,
    patience,
    currentStep,
    isOver,
    onEnd,
    onWrapUp,
}: {
    persona: Persona;
    patience: number;
    currentStep: number;
    isOver: boolean;
    onEnd: () => void;
    onWrapUp: () => void;
}) {
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const progress = Math.min(currentStep / SCRIPT_SECTIONS.length, 1) * 100;

    useEffect(() => {
        if (isOver) {
            return;
        }

        const interval = setInterval(
            () => setElapsedSeconds((seconds) => seconds + 1),
            1000,
        );

        return () => clearInterval(interval);
    }, [isOver]);

    return (
        <Card
            className={cn(
                'relative gap-0 overflow-hidden border-0 py-0 text-white',
                brandGradientClass,
            )}
        >
            <div className="flex flex-wrap items-center gap-x-6 gap-y-4 p-4 sm:p-5">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="relative">
                        <div className="flex size-12 items-center justify-center rounded-full bg-white/15 text-sm font-bold ring-2 ring-white/30">
                            {initialsOf(persona.lead.name)}
                        </div>
                        <span
                            className={cn(
                                'absolute right-0 bottom-0 size-3.5 rounded-full ring-2 ring-[#5a4177]',
                                isOver ? 'bg-white/50' : 'bg-emerald-400',
                            )}
                        />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-lg font-semibold">
                            {persona.lead.name}
                        </p>
                        <p className="text-sm text-white/70">
                            {persona.lead.state} · {persona.lead.zip}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col">
                    <span className="text-xs tracking-wide text-white/60 uppercase">
                        {isOver ? 'Call ended' : 'On call'}
                    </span>
                    <span className="font-mono text-lg font-semibold tabular-nums">
                        {formatDuration(elapsedSeconds)}
                    </span>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                        Level {persona.level.level} · {persona.level.name}
                    </span>
                    {persona.quirk && (
                        <span className="flex items-center gap-1.5 rounded-full bg-[#f598ff]/25 px-3 py-1 text-xs font-medium">
                            <Sparkles className="size-3" />
                            {persona.quirk}
                        </span>
                    )}
                </div>

                <div className="ml-auto flex flex-wrap items-center gap-4">
                    <div className="flex flex-col gap-1">
                        <span className="text-xs tracking-wide text-white/60 uppercase">
                            Patience {patience}/{persona.patience}
                        </span>
                        <PatienceMeter
                            patience={patience}
                            max={persona.patience}
                        />
                    </div>

                    {isOver ? (
                        <Button
                            className="bg-white font-bold text-[#473364] hover:bg-white/90"
                            onClick={onWrapUp}
                        >
                            Wrap up
                            <ChevronRight />
                        </Button>
                    ) : (
                        <Button
                            variant="destructive"
                            className="font-bold"
                            onClick={onEnd}
                        >
                            <PhoneOff />
                            End call
                        </Button>
                    )}
                </div>
            </div>

            <div className="h-1 bg-white/10">
                <div
                    className="h-full bg-[#f598ff] transition-[width] duration-500"
                    style={{ width: `${progress}%` }}
                />
            </div>
        </Card>
    );
}

function PatienceMeter({ patience, max }: { patience: number; max: number }) {
    return (
        <div
            className="flex items-center gap-0.5"
            role="meter"
            aria-label="Patience"
            aria-valuenow={patience}
            aria-valuemin={0}
            aria-valuemax={max}
        >
            {Array.from({ length: max }, (_, index) => (
                <Heart
                    key={index}
                    className={cn(
                        'size-4 transition-colors',
                        index < patience
                            ? 'fill-[#f598ff] text-[#f598ff]'
                            : 'text-white/30',
                    )}
                />
            ))}
        </div>
    );
}

function Conversation({
    persona,
    agentName,
    call,
    isObjectionOpen,
    onEnd,
    onWrapUp,
}: {
    persona: Persona;
    agentName: string;
    call: RoleplayCall;
    isObjectionOpen: boolean;
    onEnd: () => void;
    onWrapUp: () => void;
}) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const isOver = call.endReason !== null;
    const nextSection = SCRIPT_SECTIONS[call.currentStep];

    useEffect(() => {
        const container = scrollRef.current;
        container?.scrollTo({
            top: container.scrollHeight,
            behavior: 'smooth',
        });
    }, [call.transcript.length]);

    return (
        <Card
            className={cn(
                'order-first flex h-[36rem] flex-col gap-0 py-0 lg:order-none lg:h-[44rem]',
                resourceCardClass,
                'hover:shadow-none',
            )}
        >
            <div className="flex items-center justify-between gap-2 border-b px-5 py-3">
                <p className="font-semibold">Conversation</p>
                <span className="text-muted-foreground text-xs">
                    Mock call · AI consumer coming soon
                </span>
            </div>

            <div
                ref={scrollRef}
                className="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-4"
            >
                {call.transcript.map((line) => (
                    <TranscriptBubble
                        key={line.id}
                        line={line}
                        agentInitials={initialsOf(agentName)}
                        consumerInitials={initialsOf(persona.lead.name)}
                    />
                ))}
            </div>

            <div className="bg-muted/40 border-t px-5 py-3">
                {isOver ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-sm">
                            {call.endReason === 'hung_up'
                                ? 'The consumer hung up.'
                                : 'You ended the call.'}
                        </p>
                        <Button className={brandButtonClass} onClick={onWrapUp}>
                            Wrap up
                            <ChevronRight />
                        </Button>
                    </div>
                ) : !nextSection ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-sm">
                            Script complete. Stay quiet while the agent
                            connects.
                        </p>
                        <Button className={brandButtonClass} onClick={onEnd}>
                            End & code the call
                            <ChevronRight />
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-wrap items-center justify-between gap-3">
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
                            className={cn(!isObjectionOpen && brandButtonClass)}
                            variant={isObjectionOpen ? 'outline' : 'default'}
                            onClick={call.readStep}
                        >
                            Read step {call.currentStep + 1}
                            <ChevronRight />
                        </Button>
                    </div>
                )}
            </div>
        </Card>
    );
}

function TranscriptBubble({
    line,
    agentInitials,
    consumerInitials,
}: {
    line: TranscriptLine;
    agentInitials: string;
    consumerInitials: string;
}) {
    if (line.speaker === 'system') {
        return (
            <div className="flex justify-center">
                <p className="bg-muted text-muted-foreground max-w-[90%] rounded-full px-3 py-1 text-center text-xs">
                    {line.text}
                </p>
            </div>
        );
    }

    const isAgent = line.speaker === 'agent';

    return (
        <div
            className={cn(
                'animate-in fade-in slide-in-from-bottom-1 flex items-end gap-2',
                isAgent && 'flex-row-reverse',
            )}
        >
            <span
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                    isAgent
                        ? cn('text-white', brandGradientClass)
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {isAgent ? agentInitials : consumerInitials}
            </span>
            <div
                className={cn(
                    'max-w-[80%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed',
                    isAgent
                        ? cn('rounded-br-sm text-white', brandGradientClass)
                        : 'bg-muted rounded-bl-sm',
                )}
            >
                {line.text}
            </div>
        </div>
    );
}
