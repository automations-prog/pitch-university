import { useState } from 'react';
import { RichTextContent } from '@/components/rich-text-content';
import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    CheckCircle2,
    Lightbulb,
    MessageCircleWarning,
    MessageSquareQuote,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import type {
    CourseBlock,
    CourseCalloutBlock,
    CourseCheckBlock,
    CourseRebuttalBlock,
    CourseScriptBlock,
    CourseScriptLine,
} from '@/types';

export function CourseBlocks({ blocks }: { blocks: CourseBlock[] }) {
    return (
        <div className="space-y-6">
            {blocks.map((block, index) => (
                <CourseBlockItem key={index} block={block} />
            ))}
        </div>
    );
}

function CourseBlockItem({ block }: { block: CourseBlock }) {
    switch (block.type) {
        case 'text':
            return (
                <RichTextContent
                    html={(block as { html: string }).html}
                    className="text-base"
                />
            );
        case 'callout':
            return <CalloutBlock block={block as CourseCalloutBlock} />;
        case 'check':
            return <CheckBlock block={block as CourseCheckBlock} />;
        case 'script':
            return <ScriptBlock block={block as CourseScriptBlock} />;
        case 'rebuttal':
            return <RebuttalBlock block={block as CourseRebuttalBlock} />;
        default:
            return null;
    }
}

/**
 * Styling per callout tone. Unknown tones fall back to `tip`.
 */
const calloutTones = {
    tip: {
        icon: Lightbulb,
        box: 'border-[#c774ff] bg-[#f598ff]/10 dark:bg-[#f598ff]/10',
        iconColor: 'text-[#7a3fa0] dark:text-[#f5b8ff]',
    },
    warning: {
        icon: AlertTriangle,
        box: 'border-amber-500 bg-amber-50 dark:bg-amber-500/10',
        iconColor: 'text-amber-600 dark:text-amber-400',
    },
    compliance: {
        icon: ShieldCheck,
        box: 'border-sky-500 bg-sky-50 dark:bg-sky-500/10',
        iconColor: 'text-sky-700 dark:text-sky-300',
    },
} as const;

function CalloutBlock({ block }: { block: CourseCalloutBlock }) {
    const tone =
        calloutTones[block.tone as keyof typeof calloutTones] ??
        calloutTones.tip;
    const Icon = tone.icon;

    return (
        <div className={cn('rounded-xl border-l-4 p-4', tone.box)}>
            {block.title && (
                <div className="mb-2 flex items-center gap-2 font-semibold">
                    <Icon className={cn('size-4', tone.iconColor)} />
                    {block.title}
                </div>
            )}
            <RichTextContent html={block.html} />
        </div>
    );
}

/**
 * A scripted exchange: the agent's lines (what the trainee reads, verbatim),
 * sample consumer replies, and stage notes such as "(Code as DQ)".
 */
function ScriptBlock({ block }: { block: CourseScriptBlock }) {
    return (
        <div className="overflow-hidden rounded-xl border">
            {block.title && (
                <div className="bg-muted/50 flex items-center gap-2 border-b px-4 py-2 text-sm font-semibold">
                    <MessageSquareQuote className="size-4 text-[#7a3fa0] dark:text-[#f5b8ff]" />
                    {block.title}
                </div>
            )}
            <div className="space-y-3 p-4">
                {block.lines.map((line, index) => (
                    <ScriptLine key={index} line={line} />
                ))}
            </div>
        </div>
    );
}

function ScriptLine({ line }: { line: CourseScriptLine }) {
    if (line.speaker === 'note') {
        return (
            <p className="text-muted-foreground border-l-2 border-dashed pl-3 text-sm italic">
                {line.text}
            </p>
        );
    }

    const isAgent = line.speaker === 'agent';

    return (
        <div className="flex flex-col gap-1 sm:flex-row sm:gap-3">
            <span
                className={cn(
                    'w-20 shrink-0 pt-0.5 text-xs font-semibold tracking-wide uppercase',
                    isAgent
                        ? 'text-[#7a3fa0] dark:text-[#f5b8ff]'
                        : 'text-muted-foreground',
                )}
            >
                {isAgent ? 'You' : 'Consumer'}
            </span>
            <p
                className={cn(
                    'flex-1 rounded-lg px-3 py-2 text-sm',
                    isAgent
                        ? 'bg-[#f598ff]/10 font-medium dark:bg-[#f598ff]/10'
                        : 'bg-muted/60',
                )}
            >
                {line.text}
            </p>
        </div>
    );
}

/**
 * An objection card: what the consumer says, the exact rebuttal to read, and
 * an optional coaching note on delivery.
 */
function RebuttalBlock({ block }: { block: CourseRebuttalBlock }) {
    return (
        <div className="overflow-hidden rounded-xl border">
            <div className="bg-muted/50 flex items-start gap-2 border-b px-4 py-3">
                <MessageCircleWarning className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                <div>
                    <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                        Consumer says
                    </p>
                    <p className="font-semibold">"{block.objection}"</p>
                </div>
            </div>
            <div className="space-y-3 p-4">
                <div className="space-y-1">
                    <p className="text-xs font-semibold tracking-wide text-[#7a3fa0] uppercase dark:text-[#f5b8ff]">
                        You say (word for word)
                    </p>
                    <p className="rounded-lg bg-[#f598ff]/10 px-3 py-2 text-sm font-medium dark:bg-[#f598ff]/10">
                        {block.response}
                    </p>
                </div>
                {block.coach_note && (
                    <p className="text-muted-foreground flex items-start gap-2 text-sm">
                        <Lightbulb className="mt-0.5 size-4 shrink-0" />
                        <span>
                            <span className="text-foreground font-semibold">
                                Coach note:
                            </span>{' '}
                            {block.coach_note}
                        </span>
                    </p>
                )}
            </div>
        </div>
    );
}

function CheckBlock({ block }: { block: CourseCheckBlock }) {
    const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
    const isAnswered = selectedIndex !== null;
    const isCorrect = selectedIndex === block.answer_index;

    return (
        <div className="bg-muted/40 space-y-3 rounded-xl border p-4">
            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                Quick check
            </p>
            <p className="font-medium">{block.question}</p>

            <ChoiceList
                choices={block.choices}
                selectedIndex={selectedIndex}
                correctIndex={isAnswered ? block.answer_index : null}
                onSelect={isAnswered ? undefined : setSelectedIndex}
            />

            {isAnswered && (
                <div
                    className={cn(
                        'flex items-start gap-2 rounded-lg p-3 text-sm',
                        isCorrect
                            ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200'
                            : 'bg-red-50 text-red-900 dark:bg-red-500/10 dark:text-red-200',
                    )}
                >
                    {isCorrect ? (
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                    ) : (
                        <XCircle className="mt-0.5 size-4 shrink-0" />
                    )}
                    <div className="space-y-1">
                        <p className="font-semibold">
                            {isCorrect ? 'Correct!' : 'Not quite.'}
                        </p>
                        {block.explanation && <p>{block.explanation}</p>}
                        {!isCorrect && (
                            <button
                                type="button"
                                className="font-semibold underline"
                                onClick={() => setSelectedIndex(null)}
                            >
                                Try again
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * A list of answer choices. When `correctIndex` is given, the correct choice
 * and a wrong selection are highlighted. Omit `onSelect` to make it read-only.
 */
export function ChoiceList({
    choices,
    selectedIndex,
    correctIndex = null,
    onSelect,
    name,
}: {
    choices: string[];
    selectedIndex: number | null;
    correctIndex?: number | null;
    onSelect?: (index: number) => void;
    name?: string;
}) {
    const isRevealed = correctIndex !== null;

    return (
        <div className="grid gap-2">
            {choices.map((choice, index) => {
                const isSelected = selectedIndex === index;
                const isCorrectChoice = isRevealed && index === correctIndex;
                const isWrongSelection =
                    isRevealed && isSelected && index !== correctIndex;

                return (
                    <button
                        key={index}
                        type="button"
                        name={name}
                        disabled={!onSelect}
                        aria-pressed={isSelected}
                        onClick={() => onSelect?.(index)}
                        className={cn(
                            'bg-background flex w-full items-center gap-3 rounded-lg border px-4 py-3 text-left text-sm transition-colors',
                            onSelect &&
                                'hover:border-[#e6cdf7] hover:bg-[#f598ff]/5',
                            isSelected &&
                                !isRevealed &&
                                'border-[#c774ff] bg-[#f598ff]/10 ring-1 ring-[#c774ff]',
                            isCorrectChoice &&
                                'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10',
                            isWrongSelection &&
                                'border-red-500 bg-red-50 dark:bg-red-500/10',
                            !onSelect && 'cursor-default',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                isSelected &&
                                    !isRevealed &&
                                    'border-[#c774ff] bg-[#c774ff] text-white',
                                isCorrectChoice &&
                                    'border-emerald-500 bg-emerald-500 text-white',
                                isWrongSelection &&
                                    'border-red-500 bg-red-500 text-white',
                            )}
                        >
                            {String.fromCharCode(65 + index)}
                        </span>
                        <span className="flex-1">{choice}</span>
                        {isCorrectChoice && (
                            <CheckCircle2 className="size-4 text-emerald-600" />
                        )}
                        {isWrongSelection && (
                            <XCircle className="size-4 text-red-600" />
                        )}
                    </button>
                );
            })}
        </div>
    );
}
