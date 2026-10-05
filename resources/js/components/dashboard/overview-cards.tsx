import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as screeningIndex } from '@/routes/admin/screening';
import type {
    DashboardStats,
    ScreeningOverview,
    TrackOverviewRow,
} from '@/types';

const panelClass = 'bg-card rounded-2xl border p-5';

function Stat({
    value,
    label,
    dotClass,
}: {
    value: number;
    label: string;
    dotClass: string;
}) {
    return (
        <div>
            <p className="text-3xl font-bold tracking-tight">{value}</p>
            <p className="text-muted-foreground mt-1 flex items-center gap-2 text-sm font-medium">
                <span className={cn('size-1.5 rounded-full', dotClass)} />
                {label}
            </p>
        </div>
    );
}

export function StatsGrid({ stats }: { stats: DashboardStats }) {
    return (
        <div className={cn(panelClass, 'grid grid-cols-2 gap-6')}>
            <Stat
                value={stats.total_agents}
                label="Total agents"
                dotClass="bg-muted-foreground"
            />
            <Stat
                value={stats.active_agents}
                label="Active"
                dotClass="bg-teal-300"
            />
            <Stat
                value={stats.inactive_agents}
                label="Inactive"
                dotClass="bg-muted-foreground"
            />
            <Stat
                value={stats.total_trainings}
                label="Training tracks"
                dotClass="bg-amber-300"
            />
        </div>
    );
}

export function TrackProgressCard({
    track,
    onSeeProgress,
}: {
    track: TrackOverviewRow;
    onSeeProgress: () => void;
}) {
    const started = track.assigned - track.not_started;
    const percent =
        track.assigned > 0 ? Math.round((started / track.assigned) * 100) : 0;

    return (
        <div className={panelClass}>
            <div className="flex items-center justify-between gap-4">
                <h3 className="truncate font-semibold">
                    {track.name} training
                </h3>
                {track.average_score !== null && (
                    <span className="shrink-0 text-xs font-semibold text-teal-600 dark:text-teal-300">
                        {track.average_score}% avg. score
                    </span>
                )}
            </div>
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={track.assigned}
                aria-valuenow={started}
                className="bg-muted mt-3 h-2 overflow-hidden rounded-full"
            >
                <div
                    className="h-full rounded-full bg-sky-300 transition-all"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <p className="text-muted-foreground mt-3 text-sm">
                {track.assigned === 0
                    ? 'No agents assigned yet'
                    : `${started} of ${track.assigned} agents started · ${track.certified} certified`}
            </p>
            <Button
                type="button"
                variant="outline"
                className="mt-4 rounded-xl font-semibold"
                onClick={onSeeProgress}
            >
                See training progress
            </Button>
        </div>
    );
}

export function ScreeningSummaryCard({
    screenings,
}: {
    screenings: ScreeningOverview;
}) {
    const { stats } = screenings;

    return (
        <div className={panelClass}>
            <div className="flex items-center justify-between gap-4">
                <h3 className="font-semibold">Screening</h3>
                {stats.awaiting_review > 0 && (
                    <span className="text-xs font-semibold text-amber-600 dark:text-amber-300">
                        {stats.awaiting_review} ready to review
                    </span>
                )}
            </div>
            <p className="text-muted-foreground mt-3 text-sm">
                {stats.responses} responses · {stats.awaiting_call} waiting on
                the AI call · {stats.reviewed} reviewed
            </p>
            <Button
                asChild
                className="mt-4 rounded-xl bg-violet-300 font-semibold text-black hover:bg-violet-200"
            >
                <Link href={screeningIndex()} prefetch>
                    Open screening board
                </Link>
            </Button>
        </div>
    );
}
