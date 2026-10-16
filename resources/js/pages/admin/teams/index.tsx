import { Head, Link, router } from '@inertiajs/react';
import { FilterX, Pencil, Plus, Trash2 } from 'lucide-react';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import { FilterSelect } from '@/components/admin/filter-select';
import { SearchFilter } from '@/components/admin/search-filter';
import { TableFooter } from '@/components/admin/table-footer';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableFilters } from '@/hooks/use-table-filters';
import { brandButtonClass, resourceCardClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as teamsIndex } from '@/routes/admin/teams';
import type { Paginated, TeamRow } from '@/types';

type Filters = {
    search: string;
    campaign: string;
    manager: string;
    per_page: string;
};

export default function TeamsIndex({
    teams,
    filters,
    perPageOptions,
    campaigns,
    managers,
}: {
    teams: Paginated<TeamRow>;
    filters: Partial<Filters>;
    perPageOptions: number[];
    campaigns: { id: number; name: string }[];
    managers: { id: number; name: string }[];
}) {
    const applyFilters = useTableFilters<Filters>(teamsIndex().url, filters);
    const hasActiveFilters = Boolean(
        filters.search || filters.campaign || filters.manager,
    );

    function clearAllFilters() {
        applyFilters({ search: '', campaign: '', manager: '' });
    }
    return (
        <>
            <Head title="Teams" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Teams
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Each team has one admin, who only sees the agents on
                            their team.
                        </p>
                    </div>
                    <Button asChild className={brandButtonClass}>
                        <Link href={TeamController.create()}>
                            <Plus />
                            New team
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <SearchFilter
                            key={filters.search ?? ''}
                            value={filters.search}
                            placeholder="Search by team name…"
                            onSearch={(search) => applyFilters({ search })}
                        />
                        <FilterSelect
                            id="campaign-filter"
                            label="Campaign"
                            allLabel="All campaigns"
                            value={filters.campaign}
                            options={campaigns.map((campaign) => ({
                                value: String(campaign.id),
                                label: campaign.name,
                            }))}
                            onChange={(campaign) => applyFilters({ campaign })}
                        />
                        <FilterSelect
                            id="manager-filter"
                            label="Admin"
                            allLabel="Any admin"
                            value={filters.manager}
                            options={[
                                { value: 'none', label: 'No admin yet' },
                                ...managers.map((manager) => ({
                                    value: String(manager.id),
                                    label: manager.name,
                                })),
                            ]}
                            onChange={(manager) => applyFilters({ manager })}
                            className="w-full sm:w-52"
                        />
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
                    <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Team</TableHead>
                                <TableHead>Campaign</TableHead>
                                <TableHead>Admin</TableHead>
                                <TableHead>Agents</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {teams.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No teams found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {teams.data.map((team) => (
                                <TableRow key={team.id}>
                                    <TableCell className="font-medium">
                                        {team.name}
                                    </TableCell>
                                    <TableCell>{team.campaign.name}</TableCell>
                                    <TableCell>
                                        {team.manager ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>{team.agents_count}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <Link
                                                    href={TeamController.edit(
                                                        team.id,
                                                    )}
                                                >
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">
                                                        Edit {team.name}
                                                    </span>
                                                </Link>
                                            </Button>
                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                    >
                                                        <Trash2 className="size-4" />
                                                        <span className="sr-only">
                                                            Delete {team.name}
                                                        </span>
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>
                                                            Delete {team.name}?
                                                        </AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            Its{' '}
                                                            {team.agents_count}{' '}
                                                            agents and its admin
                                                            stay, without a
                                                            team.
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>
                                                            Cancel
                                                        </AlertDialogCancel>
                                                        <AlertDialogAction
                                                            onClick={() =>
                                                                router.delete(
                                                                    TeamController.destroy.url(
                                                                        team.id,
                                                                    ),
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <TableFooter
                    paginator={teams}
                    perPage={filters.per_page}
                    perPageOptions={perPageOptions}
                    onPerPageChange={(perPage) =>
                        applyFilters({ per_page: perPage })
                    }
                />
            </div>
        </>
    );
}

TeamsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Teams', href: teamsIndex() },
    ],
};
