import { Bot, Gauge, ListChecks } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    Delivery,
    DeliveryScore,
    DeliveryStatus,
    RoleplaySessionResult,
} from '@/lib/roleplay-data';
import { cn } from '@/lib/utils';
import { show as showSession } from '@/routes/roleplay/sessions';

/**
 * Quick checks first, then backing off: when many trainees finish at once
 * the queue can take a few minutes to reach this call.
 */
const POLL_FAST_MS = 3_000;
const POLL_SLOW_MS = 10_000;
const POLL_FAST_FOR_MS = 60_000;
const POLL_GIVE_UP_MS = 10 * 60_000;

const SOURCE_ICONS = {
    measured: Gauge,
    existing: ListChecks,
    ai: Bot,
} as const;

const SOURCE_LABELS = {
    measured: 'Measured from the call audio',
    existing: 'From the compliance checks',
    ai: 'Judged by AI from the transcript',
} as const;

/**
 * Delivery is scored in a background job after the call is saved. While
 * it's pending, re-fetch the session until it's done, failed, or a minute
 * has passed.
 */
export function useDeliveryPolling(
    result: RoleplaySessionResult | null,
): RoleplaySessionResult | null {
    const [latest, setLatest] = useState(result);
    const current = latest?.id === result?.id ? latest : result;
    const isPending = current?.delivery_status === 'pending';
    const sessionId = current?.id;

    useEffect(() => {
        if (!isPending || sessionId === undefined) {
            return;
        }

        const startedAt = Date.now();
        let timeout: ReturnType<typeof setTimeout>;
        let isCancelled = false;

        const poll = async () => {
            try {
                const response = await fetch(showSession.url(sessionId), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (response.ok && !isCancelled) {
                    setLatest((await response.json()) as RoleplaySessionResult);
                }
            } catch {
                // Keep polling; a blip shouldn't lose the score.
            }

            const waited = Date.now() - startedAt;

            if (!isCancelled && waited < POLL_GIVE_UP_MS) {
                timeout = setTimeout(
                    poll,
                    waited < POLL_FAST_FOR_MS ? POLL_FAST_MS : POLL_SLOW_MS,
                );
            }
        };

        timeout = setTimeout(poll, POLL_FAST_MS);

        return () => {
            isCancelled = true;
            clearTimeout(timeout);
        };
    }, [isPending, sessionId]);

    return current;
}

/**
 * The `guide.md` delivery criteria, each scored 1–5. Coaching only: it
 * doesn't decide whether the call passed.
 */
export function DeliveryCard({
    status,
    delivery,
}: {
    status: DeliveryStatus | null;
    delivery: Delivery | null;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-2">
                    Delivery
                    {delivery?.overall != null && (
                        <span className="text-sm font-medium">
                            {delivery.overall.toFixed(1)} / 5
                        </span>
                    )}
                </CardTitle>
                <CardDescription>
                    {status === 'pending'
                        ? 'Reviewing how you sounded… This can take a few minutes when lots of calls finish at once. It will also be on this call’s page under Recent calls.'
                        : status === 'failed'
                          ? "The AI review didn't finish, so only the measured skills are scored."
                          : 'How you said it. Coaching only — it doesn’t change pass or fail.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {status === 'pending' || !delivery ? (
                    <DeliverySkeleton />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {delivery.criteria.map((criterion) => (
                            <CriterionRow
                                key={criterion.key}
                                criterion={criterion}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function CriterionRow({ criterion }: { criterion: DeliveryScore }) {
    const SourceIcon = SOURCE_ICONS[criterion.source];

    return (
        <li className="flex flex-col gap-1.5 rounded-lg border p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-1.5 font-medium">
                    {criterion.label}
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <SourceIcon
                                className="text-muted-foreground size-3.5"
                                aria-label={SOURCE_LABELS[criterion.source]}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {SOURCE_LABELS[criterion.source]}
                        </TooltipContent>
                    </Tooltip>
                </span>
                <span className="text-muted-foreground text-xs tabular-nums">
                    {criterion.score === null ? '—' : `${criterion.score}/5`}
                </span>
            </div>
            <ScoreBar score={criterion.score} />
            <p className="text-muted-foreground text-xs leading-relaxed">
                {criterion.feedback}
            </p>
        </li>
    );
}

function ScoreBar({ score }: { score: number | null }) {
    return (
        <div
            className="flex gap-1"
            role="meter"
            aria-valuenow={score ?? undefined}
            aria-valuemin={1}
            aria-valuemax={5}
        >
            {[1, 2, 3, 4, 5].map((step) => (
                <span
                    key={step}
                    className={cn(
                        'h-1.5 flex-1 rounded-full',
                        score !== null && step <= score
                            ? score >= 4
                                ? 'bg-emerald-500'
                                : score === 3
                                  ? 'bg-amber-500'
                                  : 'bg-destructive'
                            : 'bg-muted',
                    )}
                />
            ))}
        </div>
    );
}

function DeliverySkeleton() {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {Array.from({ length: 6 }, (_, index) => (
                <div
                    key={index}
                    className="flex flex-col gap-2 rounded-lg border p-3"
                >
                    <Skeleton className="h-4 w-1/2 animate-pulse" />
                    <Skeleton className="h-1.5 w-full animate-pulse" />
                    <Skeleton className="h-3 w-3/4 animate-pulse" />
                </div>
            ))}
        </div>
    );
}
