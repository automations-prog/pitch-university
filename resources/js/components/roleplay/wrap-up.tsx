import {
    CheckCircle2,
    RotateCcw,
    SlidersHorizontal,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import type { EndReason } from '@/hooks/use-roleplay-call';
import {
    COMPLIANCE_GUIDELINES,
    DISPOSITIONS,
    OUTCOME_DISPOSITION,
    OUTCOME_LABELS,
    SCRIPT_SECTIONS,
    rebuttalFor,
    type Objection,
} from '@/lib/roleplay-data';
import type { Persona } from '@/lib/roleplay-persona';
import { brandButtonClass, brandSelectedClass } from '@/lib/brand-theme';
import { cn } from '@/lib/utils';

export function WrapUp({
    persona,
    endReason,
    stepsRead,
    facedObjections,
    onRetry,
    onChangeLevel,
}: {
    persona: Persona;
    endReason: EndReason;
    stepsRead: number;
    facedObjections: Objection[];
    onRetry: () => void;
    onChangeLevel: () => void;
}) {
    const [dispositionId, setDispositionId] = useState<string | null>(null);
    const correctDispositionId = OUTCOME_DISPOSITION[persona.outcome];
    const correctDisposition = DISPOSITIONS.find(
        (disposition) => disposition.id === correctDispositionId,
    )!;
    const isCorrect =
        endReason === 'agent' && dispositionId === correctDispositionId;

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_24rem]">
            <Card>
                <CardHeader>
                    <CardTitle>Disposition the call</CardTitle>
                    <CardDescription>
                        {endReason === 'hung_up'
                            ? 'The consumer hung up on you. Pick how you would code it.'
                            : 'Pick the disposition this call should be coded as.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-2 sm:grid-cols-2">
                    {DISPOSITIONS.map((disposition) => (
                        <button
                            key={disposition.id}
                            type="button"
                            disabled={dispositionId !== null}
                            onClick={() => setDispositionId(disposition.id)}
                            className={cn(
                                'rounded-lg border p-3 text-left text-sm transition-colors enabled:hover:border-[#e6cdf7]',
                                dispositionId === disposition.id &&
                                    brandSelectedClass,
                                dispositionId !== null &&
                                    disposition.id === correctDispositionId &&
                                    'border-emerald-500',
                            )}
                        >
                            <p className="font-semibold">{disposition.name}</p>
                            <p className="text-muted-foreground">
                                {disposition.description}
                            </p>
                        </button>
                    ))}
                </CardContent>
            </Card>

            <div className="flex flex-col gap-4">
                {dispositionId !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {isCorrect ? (
                                    <CheckCircle2 className="size-5 text-emerald-500" />
                                ) : (
                                    <XCircle className="text-destructive size-5" />
                                )}
                                {isCorrect
                                    ? 'Correct outcome'
                                    : 'Wrong outcome'}
                            </CardTitle>
                            <CardDescription>
                                This consumer should have ended as{' '}
                                <span className="text-foreground font-medium">
                                    {OUTCOME_LABELS[persona.outcome]}
                                </span>{' '}
                                — coded as {correctDisposition.name}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 text-sm">
                            {endReason === 'hung_up' && (
                                <p className="text-destructive">
                                    The consumer ran out of patience, so the
                                    call can't pass.
                                </p>
                            )}

                            {persona.dqTrap && (
                                <div>
                                    <p className="font-semibold">DQ trap</p>
                                    <p className="text-muted-foreground">
                                        {persona.dqTrap.hiddenTruth}
                                    </p>
                                </div>
                            )}

                            <div>
                                <p className="font-semibold">
                                    Script steps read
                                </p>
                                <p className="text-muted-foreground">
                                    {stepsRead} of {SCRIPT_SECTIONS.length}
                                </p>
                            </div>

                            <div className="flex flex-col gap-2">
                                <p className="font-semibold">
                                    Objections raised
                                </p>
                                {facedObjections.length === 0 ? (
                                    <p className="text-muted-foreground">
                                        None this call.
                                    </p>
                                ) : (
                                    facedObjections.map((objection) => (
                                        <div
                                            key={objection.id}
                                            className="rounded-md border p-2"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium">
                                                    {objection.meaning}
                                                </span>
                                                <Badge variant="outline">
                                                    Lost {objection.lostPercent}
                                                    %
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {
                                                    rebuttalFor(objection)
                                                        .lines[0]
                                                }
                                            </p>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                        <CardFooter className="flex flex-wrap gap-2">
                            <Button
                                className={brandButtonClass}
                                onClick={onRetry}
                            >
                                <RotateCcw />
                                Try again
                            </Button>
                            <Button variant="outline" onClick={onChangeLevel}>
                                <SlidersHorizontal />
                                Change level
                            </Button>
                        </CardFooter>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Compliance self-check</CardTitle>
                        <CardDescription>
                            Tick everything you did on this call.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 text-sm">
                        {COMPLIANCE_GUIDELINES.map((guideline, index) => (
                            <label
                                key={guideline}
                                htmlFor={`guideline-${index}`}
                                className="flex items-start gap-2"
                            >
                                <Checkbox
                                    id={`guideline-${index}`}
                                    className="mt-0.5"
                                />
                                <span>{guideline}</span>
                            </label>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
