import { Head, Link, router } from '@inertiajs/react';
import { FilterX, Pencil, Plus, Trash2 } from 'lucide-react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
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
import { Badge } from '@/components/ui/badge';
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
import { index as campaignsIndex } from '@/routes/admin/campaigns';
import type { Campaign, Paginated } from '@/types';

type Filters = {
    search: string;
    status: string;
    per_page: string;
};

export default function CampaignsIndex({
    campaigns,
    filters,
    perPageOptions,
}: {
    campaigns: Paginated<Campaign>;
    filters: Partial<Filters>;
    perPageOptions: number[];
}) {
    const applyFilters = useTableFilters<Filters>(
        campaignsIndex().url,
        filters,
    );
    const hasActiveFilters = Boolean(filters.search || filters.status);

    function clearAllFilters() {
        applyFilters({ search: '', status: '' });
    }
    return (
        <>
            <Head title="Campaigns" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Campaigns
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Group teams by the campaign they work.
                        </p>
                    </div>
                    <Button asChild className={brandButtonClass}>
                        <Link href={CampaignController.create()}>
                            <Plus />
                            New campaign
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <SearchFilter
                            key={filters.search ?? ''}
                            value={filters.search}
                            placeholder="Search by campaign name…"
                            onSearch={(search) => applyFilters({ search })}
                        />
                        <FilterSelect
                            id="status-filter"
                            label="Status"
                            allLabel="All statuses"
                            value={filters.status}
                            options={[
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ]}
                            onChange={(status) => applyFilters({ status })}
                            className="w-full sm:w-40"
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
                                <TableHead>Name</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Teams</TableHead>
                                <TableHead>Agents</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {campaigns.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No campaigns found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {campaigns.data.map((campaign) => (
                                <TableRow key={campaign.id}>
                                    <TableCell className="font-medium">
                                        {campaign.name}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                campaign.is_active
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {campaign.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {campaign.teams_count}
                                    </TableCell>
                                    <TableCell>
                                        {campaign.agents_count}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <Link
                                                    href={CampaignController.edit(
                                                        campaign.id,
                                                    )}
                                                >
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">
                                                        Edit {campaign.name}
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
                                                            Delete{' '}
                                                            {campaign.name}
                                                        </span>
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>
                                                            Delete{' '}
                                                            {campaign.name}?
                                                        </AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            This also deletes
                                                            its{' '}
                                                            {
                                                                campaign.teams_count
                                                            }{' '}
                                                            teams. Their members
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
                                                                    CampaignController.destroy.url(
                                                                        campaign.id,
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
                    paginator={campaigns}
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

CampaignsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Campaigns', href: campaignsIndex() },
    ],
};
