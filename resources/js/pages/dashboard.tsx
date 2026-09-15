import { Head, router } from '@inertiajs/react';
import { MouseEvent } from 'react';
import AgentDashboardCharts from '@/components/agent-dashboard-charts';
import DashboardCharts from '@/components/dashboard-charts';
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
import { scoreBadgeVariant } from '@/lib/scoring';
import { dashboard } from '@/routes';
import {
    GraduationCap,
    Info,
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
          licenses: DashboardFilterOption[];
          trainings: DashboardFilterOption[];
          filters: {
              status?: string;
              license?: string;
              vertical_training?: string;
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
    value: number;
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
                            Training completion and roleplay scoring, at a
                            glance.
                        </p>
                    </div>

                    <div className="flex items-start gap-2 rounded-lg border border-[#f598ff]/30 bg-[#f598ff]/10 px-4 py-3 text-sm dark:border-[#f598ff]/20 dark:bg-[#f598ff]/10">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <p>
                            Progress and scores below are sample data for
                            preview — real roleplay scoring isn&apos;t wired up
                            yet.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            label="Trainings completed"
                            value={progress.trainings_completed}
                            icon={GraduationCap}
                        />
                        <StatCard
                            label="Average score"
                            value={progress.average_score ?? 0}
                            icon={UserCheck}
                        />
                        <StatCard
                            label="Total active trainings"
                            value={progress.total_trainings}
                            icon={Users}
                        />
                    </div>

                    <AgentDashboardCharts
                        progress={progress}
                        trainingScores={trainingScores}
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Your trainings</CardTitle>
                            <CardDescription>
                                Status and score for every active training.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className={resourceCardClass}>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Training</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Score</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {trainingScores.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={3}
                                                    className="text-muted-foreground py-8 text-center"
                                                >
                                                    No active trainings yet.
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
                                                            training.trainings_completed >
                                                            0
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {training.trainings_completed >
                                                        0
                                                            ? 'Completed'
                                                            : 'Not started'}
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
        licenses,
        trainings,
        filters,
        perPageOptions,
    } = props;

    function applyFilter(
        key: 'status' | 'license' | 'vertical_training' | 'per_page',
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
                        Agent training progress and roleplay scoring, at a
                        glance.
                    </p>
                </div>

                <div className="flex items-start gap-2 rounded-lg border border-[#f598ff]/30 bg-[#f598ff]/10 px-4 py-3 text-sm dark:border-[#f598ff]/20 dark:bg-[#f598ff]/10">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Progress and scores below are sample data for preview —
                        real roleplay scoring isn&apos;t wired up yet.
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
                        label="Active trainings"
                        value={stats.total_trainings}
                        icon={GraduationCap}
                    />
                </div>

                <DashboardCharts charts={charts} />

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <div>
                            <CardTitle>Agent progress</CardTitle>
                            <CardDescription>
                                Training completion and average roleplay score
                                per agent.
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
                                value={filters.vertical_training || 'all'}
                                onValueChange={(value) =>
                                    applyFilter('vertical_training', value)
                                }
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="Vertical training" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All trainings
                                    </SelectItem>
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
                            <Table>
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
