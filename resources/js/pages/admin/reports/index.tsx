import { Head, router } from '@inertiajs/react';
import { FormEvent, MouseEvent, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { resourceCardClass, resourceInputClass } from '@/lib/brand-theme';
import { scoreBadgeVariant } from '@/lib/scoring';
import { dashboard } from '@/routes';
import { index as reportsIndex } from '@/routes/admin/reports';
import { FilterX, Search, X } from 'lucide-react';
import type {
    AgentProgress,
    DashboardFilterOption,
    Paginated,
    UserStatus,
} from '@/types';

type Filters = {
    search: string;
    status: string;
    license: string;
    vertical_training: string;
    per_page: string;
};

export default function ReportsIndex({
    agents,
    filters,
    perPageOptions,
    statuses,
    licenses,
    trainings,
}: {
    agents: Paginated<AgentProgress>;
    filters: Partial<Filters>;
    perPageOptions: number[];
    statuses: UserStatus[];
    licenses: DashboardFilterOption[];
    trainings: DashboardFilterOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilters(next: Partial<Filters>) {
        router.get(
            reportsIndex().url,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    }

    function handleSearchSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        applyFilters({ search });
    }

    // Update the table as the admin types, instead of requiring Enter.
    useEffect(() => {
        const currentSearch = filters.search ?? '';

        if (search === currentSearch) {
            return;
        }

        const timeout = setTimeout(() => applyFilters({ search }), 400);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function clearSearch() {
        setSearch('');
        if (filters.search) {
            applyFilters({ search: '' });
        }
    }

    const hasActiveFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.license ||
        filters.vertical_training,
    );

    function clearAllFilters() {
        setSearch('');
        applyFilters({
            search: '',
            status: '',
            license: '',
            vertical_training: '',
        });
    }

    return (
        <>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Reports
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Agent training completion and license status, at a
                        glance.
                    </p>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <form
                            onSubmit={handleSearchSubmit}
                            className="grid flex-1 gap-1.5 sm:min-w-56"
                        >
                            <Label
                                htmlFor="search"
                                className="text-muted-foreground text-xs font-semibold uppercase"
                            >
                                Search
                            </Label>
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    id="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name or email…"
                                    className={`pl-9 ${search ? 'pr-9' : ''} ${resourceInputClass}`}
                                />
                                {search && (
                                    <button
                                        type="button"
                                        onClick={clearSearch}
                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                    >
                                        <X className="size-4" />
                                        <span className="sr-only">
                                            Clear search
                                        </span>
                                    </button>
                                )}
                            </div>
                        </form>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="status-filter"
                                className="text-muted-foreground text-xs font-semibold uppercase"
                            >
                                Status
                            </Label>
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        status: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="status-filter"
                                    className="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="license-filter"
                                className="text-muted-foreground text-xs font-semibold uppercase"
                            >
                                License
                            </Label>
                            <Select
                                value={filters.license || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        license: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="license-filter"
                                    className="w-full sm:w-44"
                                >
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
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="training-filter"
                                className="text-muted-foreground text-xs font-semibold uppercase"
                            >
                                Vertical training
                            </Label>
                            <Select
                                value={filters.vertical_training || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        vertical_training:
                                            value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="training-filter"
                                    className="w-full sm:w-48"
                                >
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

                        {hasActiveFilters && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={clearAllFilters}
                            >
                                <FilterX />
                                Clear filters
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <div className={resourceCardClass}>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Agent</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Licenses</TableHead>
                                <TableHead>Progress</TableHead>
                                <TableHead>Avg. score</TableHead>
                                <TableHead>Last activity</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {agents.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
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
                                const agentLicenses = agent.licenses ?? [];

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
                                                    agent.status === 'active'
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                            >
                                                {agent.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {agentLicenses.length > 0 ? (
                                                <div className="flex flex-wrap items-center gap-1">
                                                    <Badge
                                                        variant={
                                                            agentLicenses[0]
                                                                .status ===
                                                            'active'
                                                                ? 'outline'
                                                                : 'destructive'
                                                        }
                                                    >
                                                        {agentLicenses[0].name}
                                                    </Badge>
                                                    {agentLicenses.length >
                                                        1 && (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="cursor-default"
                                                                >
                                                                    …
                                                                </Badge>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <ul className="space-y-0.5">
                                                                    {agentLicenses.map(
                                                                        (
                                                                            license,
                                                                        ) => (
                                                                            <li
                                                                                key={
                                                                                    license.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    license.name
                                                                                }
                                                                            </li>
                                                                        ),
                                                                    )}
                                                                </ul>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground text-sm">
                                                    —
                                                </span>
                                            )}
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
                                                    {agent.trainings_completed}/
                                                    {agent.total_trainings}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={scoreBadgeVariant(
                                                    agent.average_score,
                                                )}
                                            >
                                                {agent.average_score === null
                                                    ? '—'
                                                    : `${agent.average_score}%`}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {agent.last_activity ?? (
                                                <span className="text-muted-foreground text-sm">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <span>Rows per page</span>
                        <Select
                            value={filters.per_page ?? '10'}
                            onValueChange={(value) =>
                                applyFilters({ per_page: value })
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
                            {agents.meta.from ?? 0}–{agents.meta.to ?? 0} of{' '}
                            {agents.meta.total}
                        </span>
                    </div>

                    {agents.meta.last_page > 1 && (
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                {agents.links.map((link, index) => {
                                    const goToPage = (e: MouseEvent) => {
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
                                                    href={link.url ?? '#'}
                                                    onClick={goToPage}
                                                    className={disabledClass}
                                                />
                                            </PaginationItem>
                                        );
                                    }

                                    if (index === agents.links.length - 1) {
                                        return (
                                            <PaginationItem key={index}>
                                                <PaginationNext
                                                    href={link.url ?? '#'}
                                                    onClick={goToPage}
                                                    className={disabledClass}
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
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reports', href: reportsIndex() },
    ],
};
