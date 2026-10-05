import {
    CheckCircle2,
    MinusCircle,
    RotateCcw,
    SlidersHorizontal,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
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
    onComplete,
    onRetry,
    onChangeLevel,
}: {
    endReason: RoleplayEndReason;
    onComplete: (disposition: string) => Promise<RoleplaySessionResult>;
    onRetry: () => void;
    onChangeLevel: () => void;
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

    const correctId =
        result?.correct_disposition ??
        (result?.expected_outcome
            ? OUTCOME_DISPOSITION[result.expected_outcome]
            : null);

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_24rem]">
            <div className="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            Disposition the call
                            {dispositionId !== null && !result && <Spinner />}
                        </CardTitle>
                        <CardDescription>
                            {endReason === 'hung_up'
                                ? 'The consumer hung up on you. Pick how you would code it.'
                                : 'Pick the disposition this call should be coded as.'}
                        </CardDescription>
                        {error && (
                            <p className="text-destructive text-sm">{error}</p>
                        )}
                    </CardHeader>
                    <DispositionGrid
                        selectedId={dispositionId}
                        correctId={correctId}
                        disabled={dispositionId !== null}
                        onSelect={(disposition) => void submit(disposition)}
                    />
                </Card>

                {result && (
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
}: {
    result: RoleplaySessionResult;
    onRetry?: () => void;
    onChangeLevel?: () => void;
}) {
    const { dispositions } = useRoleplayContent();
    const outcome = result.expected_outcome ?? 'transfer';
    const correctDisposition = dispositions.find(
        (disposition) =>
            disposition.id ===
            (result.correct_disposition ?? OUTCOME_DISPOSITION[outcome]),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {result.passed ? (
                        <CheckCircle2 className="size-5 text-emerald-500" />
                    ) : (
                        <XCircle className="text-destructive size-5" />
                    )}
                    {result.passed ? 'Call passed' : 'Call failed'}
                </CardTitle>
                <CardDescription>
                    {result.correct_reason ? (
                        result.correct_reason
                    ) : (
                        <>
                            This consumer should have ended as{' '}
                            <span className="text-foreground font-medium">
                                {OUTCOME_LABELS[outcome]}
                            </span>
                            {correctDisposition &&
                                ` — coded as ${correctDisposition.name}`}
                            .
                        </>
                    )}
                </CardDescription>
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
                                    <Badge variant="outline">
                                        Lost {objection.lostPercent}%
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {objection.rebuttal.lines[0]}
                                </p>
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
                        />
                    </div>
                )}
            </CardContent>
            {onRetry && onChangeLevel && (
                <CardFooter className="flex flex-wrap gap-2">
                    <Button className={brandButtonClass} onClick={onRetry}>
                        <RotateCcw />
                        Try again
                    </Button>
                    <Button variant="outline" onClick={onChangeLevel}>
                        <SlidersHorizontal />
                        Change level
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
}
