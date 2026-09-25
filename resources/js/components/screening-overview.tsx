import { Link } from '@inertiajs/react';
import { DistributionBarChart } from '@/components/dashboard-charts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { resourceCardClass } from '@/lib/brand-theme';
import { index as screeningIndex } from '@/routes/admin/screening';
import { show as screeningResponseShow } from '@/routes/admin/screening-responses';
import { ArrowRight } from 'lucide-react';
import type { CallRating, ScreeningOverview } from '@/types';

const gutCheckVariants: Record<
    CallRating,
    'outline' | 'secondary' | 'destructive'
> = {
    yes: 'outline',
    somewhat: 'secondary',
    no: 'destructive',
};

function ScreeningStat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border p-3">
            <p className="text-2xl font-semibold tracking-tight">{value}</p>
            <p className="text-muted-foreground text-xs">{label}</p>
        </div>
    );
}

function ScreeningStatus({
    response,
}: {
    response: ScreeningOverview['recent'][number];
}) {
    if (response.overall_gut_check) {
        return (
            <Badge variant={gutCheckVariants[response.overall_gut_check]}>
                Gut check: {response.overall_gut_check}
            </Badge>
        );
    }

    return (
        <Badge variant="secondary">
            {response.called_at ? 'Awaiting review' : 'Awaiting call'}
        </Badge>
    );
}

/**
 * The screening funnel on the admin dashboard: candidates who responded, were
 * called by the AI voice agent, and were rated, plus the latest responses.
 */
export function ScreeningOverviewCard({
    screenings,
}: {
    screenings: ScreeningOverview;
}) {
    const { stats, gut_check, recent } = screenings;

    return (
        <Card>
            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                <div>
                    <CardTitle>Screenings</CardTitle>
                    <CardDescription>
                        Candidates who responded, took the AI call, and were
                        rated.
                    </CardDescription>
                </div>
                <Button asChild variant="outline" size="sm">
                    <Link href={screeningIndex()}>
                        View all
                        <ArrowRight />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <ScreeningStat label="Responses" value={stats.responses} />
                    <ScreeningStat
                        label="Awaiting call"
                        value={stats.awaiting_call}
                    />
                    <ScreeningStat label="Calls completed" value={stats.called} />
                    <ScreeningStat
                        label="Awaiting review"
                        value={stats.awaiting_review}
                    />
                    <ScreeningStat label="Reviewed" value={stats.reviewed} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-2">
                        <p className="text-sm font-medium">Overall gut check</p>
                        <DistributionBarChart data={gut_check} />
                    </div>

                    <div className="space-y-2 lg:col-span-2">
                        <p className="text-sm font-medium">Latest responses</p>
                        <div className={resourceCardClass}>
                            <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Candidate</TableHead>
                                        <TableHead>Submitted</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Details
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="text-muted-foreground py-8 text-center"
                                            >
                                                No screening responses yet.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {recent.map((response) => (
                                        <TableRow key={response.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {response.full_name}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {response.email}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {new Date(
                                                    response.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <ScreeningStatus
                                                    response={response}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={screeningResponseShow(
                                                            response.id,
                                                        )}
                                                    >
                                                        View
                                                        <ArrowRight />
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
