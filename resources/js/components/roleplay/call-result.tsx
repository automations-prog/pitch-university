import {
    AlertTriangle,
    CheckCircle2,
    MinusCircle,
    RotateCcw,
    SlidersHorizontal,
    XCircle,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { SyntheticEvent } from 'react';
import {
    DeliveryCard,
    useDeliveryPolling,
} from '@/components/roleplay/delivery-card';
import { DispositionGrid } from '@/components/roleplay/wrap-up';
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
import { Spinner } from '@/components/ui/spinner';
import type { RoleplayEndReason } from '@/hooks/use-roleplay-realtime-call';
import { brandButtonClass } from '@/lib/brand-theme';
import { useRoleplayContent } from '@/lib/roleplay-content';
import {
    OUTCOME_DISPOSITION,
    OUTCOME_LABELS,
    type RoleplaySessionResult,
} from '@/lib/roleplay-data';

/**
 * Live-call wrap-up: the trainee codes the call, the server grades it, and
 * the persona's hidden outcome, objections and DQ trap are revealed.
 */
export function CallResult({
    endReason,
    requiresDisposition = true,
    onComplete,
    onRetry,
    onChangeLevel,
    changeLevelLabel,
}: {
    endReason: RoleplayEndReason;
    /** A training scene that stops before the end of the call isn't coded. */
    requiresDisposition?: boolean;
    onComplete: (disposition: string) => Promise<RoleplaySessionResult>;
    onRetry: () => void;
    onChangeLevel: () => void;
    changeLevelLabel?: string;
}) {
    const [dispositionId, setDispositionId] = useState<string | null>(null);
    const [submitted, setSubmitted] = useState<RoleplaySessionResult | null>(
        null,
    );
    const result = useDeliveryPolling(submitted);
    const [error, setError] = useState<string | null>(null);

    const submit = async (disposition: string) => {
        setDispositionId(disposition);
        setError(null);

        try {
            setSubmitted(await onComplete(disposition));
        } catch (caught) {
            setDispositionId(null);
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Could not save the call.',
            );
        }
    };

    // Submit an uncoded scene as soon as it ends, once.
    const isAutoSubmitted = useRef(false);

    useEffect(() => {
        if (!requiresDisposition && !isAutoSubmitted.current) {
            isAutoSubmitted.current = true;
            void onComplete('')
                .then(setSubmitted)
                .catch((caught: unknown) =>
                    setError(
                        caught instanceof Error
                            ? caught.message
                            : 'Could not save the scene.',
                    ),
                );
        }
    }, [requiresDisposition, onComplete]);

    const correctId =
        result?.correct_disposition ??
        (result?.expected_outcome
            ? OUTCOME_DISPOSITION[result.expected_outcome]
            : null);

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_24rem]">
            <div className="flex flex-col gap-4">
                {!requiresDisposition && !result && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {error ? 'Scene not saved' : 'Saving the scene'}
                                {!error && <Spinner />}
                            </CardTitle>
                            {error && (
                                <CardDescription className="text-destructive">
                                    {error}
                                </CardDescription>
                            )}
                        </CardHeader>
                    </Card>
                )}

                {requiresDisposition && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                Disposition the call
                                {dispositionId !== null && !result && (
                                    <Spinner />
                                )}
                            </CardTitle>
                            <CardDescription>
                                {endReason === 'hung_up'
                                    ? 'The consumer hung up on you. Pick how you would code it.'
                                    : 'Pick the disposition this call should be coded as.'}
                            </CardDescription>
                            {error && (
                                <p className="text-destructive text-sm">
                                    {error}
                                </p>
                            )}
                        </CardHeader>
                        <DispositionGrid
                            selectedId={dispositionId}
                            correctId={correctId}
                            disabled={dispositionId !== null}
                            onSelect={(disposition) => void submit(disposition)}
                        />
                    </Card>
                )}

                {result?.delivery_status && (
                    <DeliveryCard
                        status={result.delivery_status ?? null}
                        delivery={result.delivery ?? null}
                    />
                )}
            </div>

            {result && (
                <ResultCard
                    result={result}
                    onRetry={onRetry}
                    onChangeLevel={onChangeLevel}
                    changeLevelLabel={changeLevelLabel}
                />
            )}
        </div>
    );
}

/**
 * The compliance grade and the revealed persona. Retry buttons only on the
 * wrap-up, not on a past call's page.
 */
export function ResultCard({
    result,
    onRetry,
    onChangeLevel,
    changeLevelLabel = 'Change level',
}: {
    result: RoleplaySessionResult;
    onRetry?: () => void;
    onChangeLevel?: () => void;
    changeLevelLabel?: string;
}) {
    const { dispositions, objectionRiskLabel } = useRoleplayContent();
    const outcome = result.expected_outcome ?? 'transfer';
    const correctDisposition = dispositions.find(
        (disposition) =>
            disposition.id ===
            (result.correct_disposition ?? OUTCOME_DISPOSITION[outcome]),
    );
    const codedDisposition = dispositions.find(
        (disposition) => disposition.id === result.disposition,
    );
    const isCodedCorrectly =
        correctDisposition !== undefined &&
        codedDisposition?.id === correctDisposition.id;
    const noun = result.scene ? 'Scene' : 'Call';
    const isCoded = result.scene?.code_call ?? true;

    if (result.not_graded_reason) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <AlertTriangle className="size-5 text-amber-500" />
                        {noun} not graded: mic not captured
                    </CardTitle>
                    <CardDescription>
                        {result.not_graded_reason}
                    </CardDescription>
                </CardHeader>
                {result.recording_url && (
                    <CardContent className="flex flex-col gap-2 text-sm">
                        <p className="font-semibold">Recording</p>
                        <audio
                            controls
                            src={result.recording_url}
                            className="w-full"
                            onLoadedMetadata={revealWebmDuration}
                        />
                    </CardContent>
                )}
                <ResultFooter
                    onRetry={onRetry}
                    onChangeLevel={onChangeLevel}
                    changeLevelLabel={changeLevelLabel}
                />
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {result.passed === null ? (
                        <Spinner />
                    ) : result.passed ? (
                        <CheckCircle2 className="size-5 text-emerald-500" />
                    ) : (
                        <XCircle className="text-destructive size-5" />
                    )}
                    {result.passed === null
                        ? `Grading your ${noun.toLowerCase()}…`
                        : `${noun} ${result.passed ? 'passed' : 'failed'}`}
                </CardTitle>
                {isCoded && (
                    <CardDescription className="flex flex-col gap-1">
                        {result.correct_reason ? (
                            <span>{result.correct_reason}</span>
                        ) : (
                            <span>
                                This consumer should have ended as{' '}
                                <span className="text-foreground font-medium">
                                    {OUTCOME_LABELS[outcome]}
                                </span>
                                .
                            </span>
                        )}
                        {correctDisposition && (
                            <span>
                                Correct code:{' '}
                                <span className="text-foreground font-medium">
                                    {correctDisposition.name}
                                </span>
                                {codedDisposition && (
                                    <>
                                        {' '}
                                        · You coded:{' '}
                                        <span className="text-foreground font-medium">
                                            {codedDisposition.name}
                                        </span>
                                    </>
                                )}
                                .
                            </span>
                        )}
                        {result.passed === false && isCodedCorrectly && (
                            <span className="text-destructive">
                                You coded it right, but the {noun.toLowerCase()}{' '}
                                failed on the check marked below.
                            </span>
                        )}
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4 text-sm">
                <ul className="flex flex-col gap-2">
                    {result.checks?.map((check) => (
                        <li key={check.key} className="flex items-start gap-2">
                            {check.passed === null ? (
                                <MinusCircle className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            ) : check.passed ? (
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                            ) : (
                                <XCircle className="text-destructive mt-0.5 size-4 shrink-0" />
                            )}
                            <span
                                className={
                                    check.passed === null
                                        ? 'text-muted-foreground'
                                        : undefined
                                }
                            >
                                {check.label}
                                {check.feedback && (
                                    <span className="text-muted-foreground block text-xs">
                                        {check.feedback}
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>

                {result.dq_trap && (
                    <div>
                        <p className="font-semibold">DQ trap</p>
                        <p className="text-muted-foreground">
                            {result.dq_trap.hidden_truth}
                        </p>
                    </div>
                )}

                {!!result.hidden_facts?.length && (
                    <div>
                        <p className="font-semibold">About this consumer</p>
                        <ul className="text-muted-foreground list-disc pl-5">
                            {result.hidden_facts.map((fact) => (
                                <li key={fact}>{fact}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex flex-col gap-2">
                    <p className="font-semibold">
                        Objections this consumer had
                    </p>
                    {!result.objections?.length ? (
                        <p className="text-muted-foreground">None this call.</p>
                    ) : (
                        result.objections.map((objection) => (
                            <div
                                key={objection.id}
                                className="rounded-md border p-2"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-medium">
                                        {objection.meaning}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        title="How often real agents get this one wrong, from recorded calls. Not your score."
                                    >
                                        Real calls: {objectionRiskLabel}{' '}
                                        {objection.lostPercent}%
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {objection.rebuttal.lines[0]}
                                </p>
                                {!!objection.topAgentLines?.length && (
                                    <div className="mt-2 text-xs">
                                        <p className="font-medium">
                                            How top agents said it
                                        </p>
                                        <ul className="text-muted-foreground mt-1 flex flex-col gap-1">
                                            {objection.topAgentLines.map(
                                                (line) => (
                                                    <li key={line}>“{line}”</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {result.recording_url && (
                    <div className="flex flex-col gap-2">
                        <p className="font-semibold">Recording</p>
                        <audio
                            controls
                            src={result.recording_url}
                            className="w-full"
                            onLoadedMetadata={revealWebmDuration}
                        />
                    </div>
                )}
            </CardContent>
            <ResultFooter
                onRetry={onRetry}
                onChangeLevel={onChangeLevel}
                changeLevelLabel={changeLevelLabel}
            />
        </Card>
    );
}

function ResultFooter({
    onRetry,
    onChangeLevel,
    changeLevelLabel,
}: {
    onRetry?: () => void;
    onChangeLevel?: () => void;
    changeLevelLabel: string;
}) {
    if (!onRetry || !onChangeLevel) {
        return null;
    }

    return (
        <CardFooter className="flex flex-wrap gap-2">
            <Button className={brandButtonClass} onClick={onRetry}>
                <RotateCcw />
                Try again
            </Button>
            <Button variant="outline" onClick={onChangeLevel}>
                <SlidersHorizontal />
                {changeLevelLabel}
            </Button>
        </CardFooter>
    );
}

/**
 * Browser-recorded webm files don't store their length, so the player
 * showed 0:00 until played through. Seeking past the end makes the
 * browser work it out; then jump back to the start.
 */
function revealWebmDuration(event: SyntheticEvent<HTMLAudioElement>) {
    const audio = event.currentTarget;

    if (Number.isFinite(audio.duration)) {
        return;
    }

    audio.addEventListener(
        'timeupdate',
        () => {
            audio.currentTime = 0;
        },
        { once: true },
    );
    audio.currentTime = Number.MAX_SAFE_INTEGER;
}
