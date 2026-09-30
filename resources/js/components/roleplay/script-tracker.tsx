import { Check, CornerDownRight } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { ScriptText } from '@/components/roleplay/script-text';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { fillScriptPlaceholders } from '@/hooks/use-roleplay-call';
import { brandGradientClass } from '@/lib/brand-theme';
import { SCRIPT_SECTIONS } from '@/lib/roleplay-data';
import type { Lead } from '@/lib/roleplay-persona';
import { cn } from '@/lib/utils';

/**
 * The script as a vertical stepper. Finished steps collapse to their title;
 * the current step opens in place with its lines and answer branches.
 */
export function ScriptTracker({
    currentStep,
    lead,
    agentName,
}: {
    currentStep: number;
    lead: Lead;
    agentName: string;
}) {
    const scrollRef = useRef<HTMLDivElement>(null);

    /**
     * Keep the current step in view as the trainee advances, scrolling only
     * the panel rather than the whole page.
     */
    useEffect(() => {
        const container = scrollRef.current;
        const current = container?.querySelector<HTMLElement>(
            '[aria-current="step"]',
        );

        if (!container || !current) {
            return;
        }

        container.scrollTo({
            top:
                current.getBoundingClientRect().top -
                container.getBoundingClientRect().top +
                container.scrollTop -
                8,
            behavior: 'smooth',
        });
    }, [currentStep]);

    return (
        <Card className="flex max-h-[44rem] flex-col gap-4">
            <CardHeader>
                <CardTitle>Script</CardTitle>
                <CardDescription>
                    Read it exactly as written. Stress the highlighted words.
                </CardDescription>
            </CardHeader>
            <CardContent ref={scrollRef} className="overflow-y-auto">
                <ol className="flex flex-col">
                    {SCRIPT_SECTIONS.map((section, index) => {
                        const isDone = index < currentStep;
                        const isCurrent = index === currentStep;
                        const isLast = index === SCRIPT_SECTIONS.length - 1;

                        return (
                            <li
                                key={section.id}
                                aria-current={isCurrent ? 'step' : undefined}
                                className="relative flex gap-3 pb-4 last:pb-0"
                            >
                                {!isLast && (
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'absolute top-6 bottom-0 left-[11px] w-0.5',
                                            isDone
                                                ? brandGradientClass
                                                : 'bg-border',
                                        )}
                                    />
                                )}

                                <span
                                    className={cn(
                                        'relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                                        isDone &&
                                            cn(
                                                'border-transparent text-white',
                                                brandGradientClass,
                                            ),
                                        isCurrent &&
                                            'bg-background border-[#f598ff] ring-4 ring-[#f598ff]/20',
                                        !isDone &&
                                            !isCurrent &&
                                            'bg-background text-muted-foreground',
                                    )}
                                >
                                    {isDone ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        index + 1
                                    )}
                                </span>

                                <div className="flex min-w-0 flex-1 flex-col gap-2 pt-0.5">
                                    <p
                                        className={cn(
                                            'text-sm leading-5',
                                            isCurrent
                                                ? 'font-semibold'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {section.title}
                                    </p>

                                    {isCurrent && (
                                        <CurrentStep
                                            sectionIndex={index}
                                            lead={lead}
                                            agentName={agentName}
                                        />
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>

                {currentStep >= SCRIPT_SECTIONS.length && (
                    <p className="text-muted-foreground mt-4 rounded-lg border border-dashed p-3 text-center text-sm">
                        Script complete. End the call and code it.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function CurrentStep({
    sectionIndex,
    lead,
    agentName,
}: {
    sectionIndex: number;
    lead: Lead;
    agentName: string;
}) {
    const section = SCRIPT_SECTIONS[sectionIndex];

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-[#f598ff]/40 bg-[#f598ff]/5 p-3 text-sm leading-relaxed">
            {section.lines.map((line) => (
                <p key={line}>
                    <ScriptText
                        text={fillScriptPlaceholders(line, lead, agentName)}
                    />
                </p>
            ))}

            {section.branches.length > 0 && (
                <ul className="flex flex-col gap-2 border-t pt-3">
                    {section.branches.map((branch) => (
                        <li key={branch.answer} className="flex gap-2 text-xs">
                            <CornerDownRight className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <span className="font-semibold">
                                    If “{branch.answer}”
                                </span>{' '}
                                <ScriptText
                                    text={branch.response}
                                    className="text-muted-foreground"
                                />
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
