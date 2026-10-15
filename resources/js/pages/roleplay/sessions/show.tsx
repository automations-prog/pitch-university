import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { ResultCard } from '@/components/roleplay/call-result';
import { DeliveryCard } from '@/components/roleplay/delivery-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    RoleplayContentContext,
    RoleplayVerticalContext,
} from '@/lib/roleplay-content';
import type {
    RoleplayContent,
    RoleplaySessionResult,
    RoleplayVertical,
} from '@/lib/roleplay-data';
import { roleplayRoutes } from '@/lib/roleplay-routes';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const TRANSCRIPT_LINE = /^\[(\d+:\d{2})\] (agent|consumer): (.*)$/;

/**
 * A past roleplay call: compliance grade, delivery scores, transcript and
 * recording.
 */
export default function RoleplaySessionShow({
    vertical,
    content,
    session,
    trail,
}: {
    vertical: RoleplayVertical;
    content: RoleplayContent;
    session: RoleplaySessionResult;
    /** Where the call was opened from, when not the roleplay page: a training module, or an agent's training for an admin. */
    trail: { title: string; href: string }[] | null;
}) {
    const roleplayIndex = roleplayRoutes(vertical.id).index;
    const breadcrumbs = trail ?? [
        { title: 'Dashboard', href: dashboard().url },
        { title: `${vertical.label} Roleplay`, href: roleplayIndex().url },
    ];
    const back = breadcrumbs[breadcrumbs.length - 1];

    setLayoutProps({ breadcrumbs });

    const level = content.levels.find(
        (candidate) => candidate.level === session.level,
    );

    return (
        <RoleplayVerticalContext value={vertical}>
            <RoleplayContentContext value={content}>
                <Head
                    title={`${vertical.label} Roleplay · ${session.lead.name}`}
                />

                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-0.5">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {session.scene ? 'Scene' : 'Call'} with{' '}
                                {session.lead.name}
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Level {session.level}
                                {level && ` · ${level.name}`}
                                {session.ended_at &&
                                    ` · ${new Date(session.ended_at).toLocaleString()}`}
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={back.href}>
                                <ChevronLeft />
                                {trail
                                    ? `Back to ${back.title}`
                                    : 'Back to roleplay'}
                            </Link>
                        </Button>
                    </div>

                    <div className="grid items-start gap-4 lg:grid-cols-[1fr_24rem]">
                        <div className="flex flex-col gap-4">
                            {!session.scene && (
                                <DeliveryCard
                                    status={session.delivery_status ?? null}
                                    delivery={session.delivery ?? null}
                                />
                            )}
                            <Transcript transcript={session.transcript ?? ''} />
                        </div>
                        <ResultCard result={session} />
                    </div>
                </div>
            </RoleplayContentContext>
        </RoleplayVerticalContext>
    );
}

function Transcript({ transcript }: { transcript: string }) {
    const lines = transcript
        // Older calls were stored with CRLF line breaks.
        .split(/\r?\n/)
        .map((line) => TRANSCRIPT_LINE.exec(line))
        .filter((match) => match !== null);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Transcript</CardTitle>
                <CardDescription>
                    As transcribed during the call.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {lines.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No transcript was captured.
                    </p>
                ) : (
                    lines.map(([, at, speaker, text], index) => (
                        <div
                            key={index}
                            className={cn(
                                'flex gap-3 text-sm',
                                speaker === 'agent' &&
                                    'flex-row-reverse text-right',
                            )}
                        >
                            <span className="text-muted-foreground w-10 shrink-0 font-mono text-xs tabular-nums">
                                {at}
                            </span>
                            <p
                                className={cn(
                                    'max-w-[80%] rounded-2xl px-3 py-2 leading-relaxed',
                                    speaker === 'agent'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted',
                                )}
                            >
                                {text}
                            </p>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
