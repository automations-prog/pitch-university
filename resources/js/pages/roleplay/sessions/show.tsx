import { Head, Link } from '@inertiajs/react';
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
import { RoleplayContentContext } from '@/lib/roleplay-content';
import type {
    RoleplayContent,
    RoleplaySessionResult,
} from '@/lib/roleplay-data';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as roleplayIndex } from '@/routes/roleplay';

const TRANSCRIPT_LINE = /^\[(\d+:\d{2})\] (agent|consumer): (.*)$/;

/**
 * A past roleplay call: compliance grade, delivery scores, transcript and
 * recording.
 */
export default function RoleplaySessionShow({
    content,
    session,
}: {
    content: RoleplayContent;
    session: RoleplaySessionResult;
}) {
    const level = content.levels.find(
        (candidate) => candidate.level === session.level,
    );

    return (
        <RoleplayContentContext value={content}>
            <Head title={`Roleplay · ${session.lead.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Call with {session.lead.name}
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Level {session.level}
                            {level && ` · ${level.name}`}
                            {session.ended_at &&
                                ` · ${new Date(session.ended_at).toLocaleString()}`}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={roleplayIndex()}>
                            <ChevronLeft />
                            Back to roleplay
                        </Link>
                    </Button>
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-[1fr_24rem]">
                    <div className="flex flex-col gap-4">
                        <DeliveryCard
                            status={session.delivery_status ?? null}
                            delivery={session.delivery ?? null}
                        />
                        <Transcript transcript={session.transcript ?? ''} />
                    </div>
                    <ResultCard result={session} />
                </div>
            </div>
        </RoleplayContentContext>
    );
}

function Transcript({ transcript }: { transcript: string }) {
    const lines = transcript
        .split('\n')
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

RoleplaySessionShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roleplay', href: roleplayIndex() },
    ],
};
