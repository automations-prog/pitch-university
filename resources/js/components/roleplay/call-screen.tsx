import { Heart, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { RebuttalsPanel } from '@/components/roleplay/rebuttals-panel';
import { ScriptTracker } from '@/components/roleplay/script-tracker';
import { Card } from '@/components/ui/card';
import { brandGradientClass, resourceCardClass } from '@/lib/brand-theme';
import { useRoleplayContent } from '@/lib/roleplay-content';
import type { Lead, Level, Rebuttal } from '@/lib/roleplay-data';
import { cn } from '@/lib/utils';

export type CallTranscriptLine = {
    id: number;
    speaker: 'agent' | 'consumer' | 'system';
    text: string;
};

/**
 * Everything the call screen shows, from either the live AI call or the
 * practice-without-mic mock.
 */
export type CallView = {
    lead: Lead;
    level: Level;
    quirk: string | null;
    patience: number;
    maxPatience: number;
    elapsedSeconds: number;
    statusLabel: string;
    isOver: boolean;
    isObjectionOpen: boolean;
    transcript: CallTranscriptLine[];
    scriptStep: number;
};

export function initialsOf(name: string): string {
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

/** A one-second ticker for calls that don't track their own time. */
export function useElapsedSeconds(isRunning: boolean): number {
    const [elapsedSeconds, setElapsedSeconds] = useState(0);

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const interval = setInterval(
            () => setElapsedSeconds((seconds) => seconds + 1),
            1000,
        );

        return () => clearInterval(interval);
    }, [isRunning]);

    return elapsedSeconds;
}

export function CallScreen({
    view,
    agentName,
    barActions,
    footer,
    conversationNote,
    onReadRebuttal,
    onScriptStepChange,
}: {
    view: CallView;
    agentName: string;
    /** Buttons on the right of the call bar (mute, end call, wrap up). */
    barActions: ReactNode;
    /** The action row under the transcript. */
    footer: ReactNode;
    conversationNote: string;
    /** Mock only: reading a rebuttal out loud. */
    onReadRebuttal?: (rebuttal: Rebuttal, lineIndex: number) => void;
    /** Live only: the trainee moves the teleprompter themselves. */
    onScriptStepChange?: (step: number) => void;
}) {
    return (
        <div className="flex flex-col gap-4">
            <CallBar view={view} actions={barActions} />

            <div className="grid items-start gap-4 lg:grid-cols-[20rem_1fr_22rem]">
                <ScriptTracker
                    currentStep={view.scriptStep}
                    lead={view.lead}
                    agentName={agentName}
                    onStepChange={view.isOver ? undefined : onScriptStepChange}
                />

                <Conversation
                    view={view}
                    agentName={agentName}
                    note={conversationNote}
                    footer={footer}
                />

                <RebuttalsPanel
                    disabled={view.isOver}
                    isObjectionOpen={view.isObjectionOpen && !view.isOver}
                    onRead={onReadRebuttal}
                />
            </div>
        </div>
    );
}

function CallBar({ view, actions }: { view: CallView; actions: ReactNode }) {
    const { scriptSections } = useRoleplayContent();
    const progress = Math.min(view.scriptStep / scriptSections.length, 1) * 100;

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
                            {initialsOf(view.lead.name)}
                        </div>
                        <span
                            className={cn(
                                'absolute right-0 bottom-0 size-3.5 rounded-full ring-2 ring-[#5a4177]',
                                view.isOver ? 'bg-white/50' : 'bg-emerald-400',
                            )}
                        />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-lg font-semibold">
                            {view.lead.name}
                        </p>
                        <p className="text-sm text-white/70">
                            {view.lead.state} · {view.lead.zip}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col">
                    <span className="text-xs tracking-wide text-white/60 uppercase">
                        {view.statusLabel}
                    </span>
                    <span className="font-mono text-lg font-semibold tabular-nums">
                        {formatDuration(view.elapsedSeconds)}
                    </span>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                        Level {view.level.level} · {view.level.name}
                    </span>
                    {view.quirk && (
                        <span className="flex items-center gap-1.5 rounded-full bg-[#f598ff]/25 px-3 py-1 text-xs font-medium">
                            <Sparkles className="size-3" />
                            {view.quirk}
                        </span>
                    )}
                </div>

                <div className="ml-auto flex flex-wrap items-center gap-4">
                    <div className="flex flex-col gap-1">
                        <span className="text-xs tracking-wide text-white/60 uppercase">
                            Patience {view.patience}/{view.maxPatience}
                        </span>
                        <PatienceMeter
                            patience={view.patience}
                            max={view.maxPatience}
                        />
                    </div>

                    {actions}
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
    view,
    agentName,
    note,
    footer,
}: {
    view: CallView;
    agentName: string;
    note: string;
    footer: ReactNode;
}) {
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = scrollRef.current;
        container?.scrollTo({
            top: container.scrollHeight,
            behavior: 'smooth',
        });
    }, [view.transcript.length]);

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
                <span className="text-muted-foreground text-xs">{note}</span>
            </div>

            <div
                ref={scrollRef}
                className="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-4"
            >
                {view.transcript.map((line) => (
                    <TranscriptBubble
                        key={line.id}
                        line={line}
                        agentInitials={initialsOf(agentName)}
                        consumerInitials={initialsOf(view.lead.name)}
                    />
                ))}
            </div>

            <div className="bg-muted/40 border-t px-5 py-3">{footer}</div>
        </Card>
    );
}

function TranscriptBubble({
    line,
    agentInitials,
    consumerInitials,
}: {
    line: CallTranscriptLine;
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
