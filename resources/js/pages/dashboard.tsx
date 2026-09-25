import { Head, router } from '@inertiajs/react';
import { MouseEvent } from 'react';
import AgentDashboardCharts from '@/components/agent-dashboard-charts';
import { CourseProgressBar } from '@/components/course-progress-bar';
import DashboardCharts from '@/components/dashboard-charts';
import { ScreeningOverviewCard } from '@/components/screening-overview';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { resourceCardClass } from '@/lib/brand-theme';
import { scoreBadgeVariant, trackStatusLabels } from '@/lib/scoring';
import { dashboard } from '@/routes';
import {
    Award,
    GraduationCap,
    type LucideIcon,
    UserCheck,
    Users,
    UserX,
} from 'lucide-react';
import type {
    AgentProgress,
    AgentProgressSummary,
    AgentTrainingScore,
    DashboardCharts as DashboardChartsData,
    DashboardFilterOption,
    DashboardStats,
    Paginated,
    ScreeningOverview,
    TrackOverviewRow,
} from '@/types';

type Props =
    | {
          isAdmin: false;
          progress: AgentProgressSummary;
          trainingScores: AgentTrainingScore[];
      }
    | {
          isAdmin: true;
          stats: DashboardStats;
          agents: Paginated<AgentProgress>;
          charts: DashboardChartsData;
          trackOverview: TrackOverviewRow[];
          screenings: ScreeningOverview;
          licenses: DashboardFilterOption[];
          trainings: DashboardFilterOption[];
          filters: {
              status?: string;
              license?: string;
              track?: string;
              per_page?: string;
          };
          perPageOptions: number[];
      };

function StatCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number | string;
    icon: LucideIcon;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3">
                <div
                    className="flex size-10 shrink-0 items-center justify-center rounded-full text-white"
                    style={{
                        background:
                            'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                    }}
                >
                    <Icon className="size-5" />
                </div>
                <div>
                    <p className="text-2xl font-semibold tracking-tight">
                        {value}
                    </p>
                    <p className="text-muted-foreground text-xs">{label}</p>
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * How the agents assigned to each training track are progressing.
 */
function TrackOverviewCard({ tracks }: { tracks: TrackOverviewRow[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Training tracks</CardTitle>
                <CardDescription>
                    Progress of the agents assigned to each track. Tracks with
                    a final exam are complete once the agent is certified. The
                    average score is each agent&apos;s best quiz score per
                    module.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className={resourceCardClass}>
                    <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Track</TableHead>
                                <TableHead>Assigned</TableHead>
                                <TableHead>Not started</TableHead>
                                <TableHead>In progress</TableHead>
                                <TableHead>Completed</TableHead>
                                <TableHead>Avg. score</TableHead>
                                <TableHead className="w-40">Completion</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tracks.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No training tracks yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {tracks.map((track) => (
                                <TableRow key={track.slug}>
                                    <TableCell className="font-medium">
                                        <span className="inline-flex items-center gap-2">
                                            {track.name}
                                            {track.has_exam && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1"
                                                >
                                                    <Award className="size-3" />
                                                    Exam
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell>{track.assigned}</TableCell>
                                    <TableCell>{track.not_started}</TableCell>
                                    <TableCell>{track.in_progress}</TableCell>
                                    <TableCell>{track.completed}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={scoreBadgeVariant(
                                                track.average_score,
                                            )}
                                        >
                                            {track.average_score === null
                                                ? '—'
                                                : `${track.average_score}%`}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {track.assigned === 0 ? (
                                            <span className="text-muted-foreground text-xs">
                                                No agents assigned
                                            </span>
                                        ) : (
                                            <div className="space-y-1">
                                                <CourseProgressBar
                                                    value={track.completed}
                                                    max={track.assigned}
                                                />
                                                <p className="text-muted-foreground text-xs">
                                                    {Math.round(
                                                        (track.completed /
                                                            track.assigned) *
                                                            100,
                                                    )}
                                                    %
                                                </p>
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard(props: Props) {
    if (!props.isAdmin) {
        const { progress, trainingScores } = props;

        return (
            <>
                <Head title="Dashboard" />

                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Your progress
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Your training progress and quiz scores, at a
                            glance.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            label="Tracks completed"
                            value={`${progress.trainings_completed}/${progress.total_trainings}`}
                            icon={GraduationCap}
                        />
                        <StatCard
                            label="Certifications"
                            value={progress.certifications}
                            icon={Award}
                        />
                        <StatCard
                            label="Average quiz score"
                            value={
                                progress.average_score === null
                                    ? '—'
                                    : `${progress.average_score}%`
                            }
                            icon={UserCheck}
                        />
                    </div>

                    <AgentDashboardCharts
                        progress={progress}
                        trainingScores={trainingScores}
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Your training scores</CardTitle>
                            <CardDescription>
                                Status and average quiz score for every
                                assigned track.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className={resourceCardClass}>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Track</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Avg. score</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {trainingScores.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={3}
                                                    className="text-muted-foreground py-8 text-center"
                                                >
                                                    No training assigned yet.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {trainingScores.map((training) => (
                                            <TableRow key={training.id}>
                                                <TableCell className="font-medium">
                                                    {training.name}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            training.status ===
                                                                'certified' ||
                                                            training.status ===
                                                                'complete'
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {
                                                            trackStatusLabels[
                                                                training.status
                                                            ]
                                                        }
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={scoreBadgeVariant(
                                                            training.average_score,
                                                        )}
                                                    >
                                                        {training.average_score ===
                                                        null
                                                            ? '—'
                                                            : `${training.average_score}%`}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </>
        );
    }

    const {
        stats,
        agents,
        charts,
        trackOverview,
        screenings,
        licenses,
        trainings,
        filters,
        perPageOptions,
    } = props;

    function applyFilter(
        key: 'status' | 'license' | 'track' | 'per_page',
        value: string,
    ) {
        router.get(
            dashboard().url,
            {
                ...filters,
                [key]: value === 'all' ? undefined : value,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Dashboard
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Agent training progress, quiz scores, and
                        screenings, at a glance.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total agents"
                        value={stats.total_agents}
                        icon={Users}
                    />
                    <StatCard
                        label="Active agents"
                        value={stats.active_agents}
                        icon={UserCheck}
                    />
                    <StatCard
                        label="Inactive agents"
                        value={stats.inactive_agents}
                        icon={UserX}
                    />
                    <StatCard
                        label="Training tracks"
                        value={stats.total_trainings}
                        icon={GraduationCap}
                    />
                </div>

                <TrackOverviewCard tracks={trackOverview} />

                <ScreeningOverviewCard screenings={screenings} />

                <DashboardCharts charts={charts} />

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <div>
                            <CardTitle>Agent progress</CardTitle>
                            <CardDescription>
                                Tracks completed and average quiz score per
                                agent.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    applyFilter('status', value)
                                }
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="active">
                                        active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.license || 'all'}
                                onValueChange={(value) =>
                                    applyFilter('license', value)
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue placeholder="License" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All licenses
                                    </SelectItem>
                                    {licenses.map((license) => (
                                        <SelectItem
                                            key={license.id}
                                            value={String(license.id)}
                                        >
                                            {license.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.track || 'all'}
                                onValueChange={(value) =>
                                    applyFilter('track', value)
                                }
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="Training track" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All tracks</SelectItem>
                                    {trainings.map((training) => (
                                        <SelectItem
                                            key={training.id}
                                            value={String(training.id)}
                                        >
                                            {training.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className={resourceCardClass}>
                            <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Agent</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Progress</TableHead>
                                        <TableHead>Avg. score</TableHead>
                                        <TableHead>Last activity</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {agents.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="text-muted-foreground py-8 text-center"
                                            >
                                                No agents found.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {agents.data.map((agent) => {
                                        const percent =
                                            agent.total_trainings > 0
                                                ? Math.round(
                                                      (agent.trainings_completed /
                                                          agent.total_trainings) *
                                                          100,
                                                  )
                                                : 0;

                                        return (
                                            <TableRow key={agent.id}>
                                                <TableCell>
                                                    <p className="font-medium">
                                                        {agent.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {agent.email}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            agent.status ===
                                                            'active'
                                                                ? 'outline'
                                                                : 'destructive'
                                                        }
                                                    >
                                                        {agent.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <div className="bg-muted h-1.5 w-24 overflow-hidden rounded-full">
                                                            <div
                                                                className="h-full rounded-full"
                                                                style={{
                                                                    width: `${percent}%`,
                                                                    background:
                                                                        'linear-gradient(135deg, #f598ff, #8a5fae)',
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="text-muted-foreground text-xs whitespace-nowrap">
                                                            {
                                                                agent.trainings_completed
                                                            }
                                                            /
                                                            {
                                                                agent.total_trainings
                                                            }
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={scoreBadgeVariant(
                                                            agent.average_score,
                                                        )}
                                                    >
                                                        {agent.average_score ===
                                                        null
                                                            ? 'Not started'
                                                            : `${agent.average_score}%`}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {agent.last_activity
                                                        ? new Date(
                                                              agent.last_activity,
                                                          ).toLocaleDateString()
                                                        : '—'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="flex flex-col items-center justify-between gap-4 pt-4 sm:flex-row">
                            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                <span>Rows per page</span>
                                <Select
                                    value={filters.per_page ?? '10'}
                                    onValueChange={(value) =>
                                        applyFilter('per_page', value)
                                    }
                                >
                                    <SelectTrigger className="w-20">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {perPageOptions.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={String(option)}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <span>
                                    {agents.meta.from ?? 0}–
                                    {agents.meta.to ?? 0} of {agents.meta.total}
                                </span>
                            </div>

                            {agents.meta.last_page > 1 && (
                                <Pagination className="mx-0 w-auto">
                                    <PaginationContent>
                                        {agents.links.map((link, index) => {
                                            const goToPage = (
                                                e: MouseEvent,
                                            ) => {
                                                e.preventDefault();
                                                if (link.url) {
                                                    router.get(
                                                        link.url,
                                                        {},
                                                        { preserveState: true },
                                                    );
                                                }
                                            };

                                            if (link.label === '...') {
                                                return (
                                                    <PaginationItem key={index}>
                                                        <PaginationEllipsis />
                                                    </PaginationItem>
                                                );
                                            }

                                            const disabledClass = !link.url
                                                ? 'pointer-events-none opacity-50'
                                                : undefined;

                                            if (index === 0) {
                                                return (
                                                    <PaginationItem key={index}>
                                                        <PaginationPrevious
                                                            href={
                                                                link.url ?? '#'
                                                            }
                                                            onClick={goToPage}
                                                            className={
                                                                disabledClass
                                                            }
                                                        />
                                                    </PaginationItem>
                                                );
                                            }

                                            if (
                                                index ===
                                                agents.links.length - 1
                                            ) {
                                                return (
                                                    <PaginationItem key={index}>
                                                        <PaginationNext
                                                            href={
                                                                link.url ?? '#'
                                                            }
                                                            onClick={goToPage}
                                                            className={
                                                                disabledClass
                                                            }
                                                        />
                                                    </PaginationItem>
                                                );
                                            }

                                            return (
                                                <PaginationItem key={index}>
                                                    <PaginationLink
                                                        href={link.url ?? '#'}
                                                        isActive={link.active}
                                                        onClick={goToPage}
                                                    >
                                                        {link.label}
                                                    </PaginationLink>
                                                </PaginationItem>
                                            );
                                        })}
                                    </PaginationContent>
                                </Pagination>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
