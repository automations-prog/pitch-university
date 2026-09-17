import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, MouseEvent, useEffect, useState } from 'react';
import VerticalTrainingController from '@/actions/App/Http/Controllers/Admin/VerticalTrainingController';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    brandButtonClass,
    resourceCardClass,
    resourceInputClass,
} from '@/lib/brand-theme';
import { index as verticalTrainingIndex } from '@/routes/admin/vertical-training';
import { dashboard } from '@/routes';
import {
    CircleCheck,
    Eye,
    FilterX,
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    PowerOff,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import type {
    Paginated,
    VerticalTraining,
    VerticalTrainingStatus,
} from '@/types';

type Filters = {
    search: string;
    status: string;
    per_page: string;
};

export default function VerticalTrainingIndex({
    trainings,
    filters,
    statuses,
    perPageOptions,
}: {
    trainings: Paginated<VerticalTraining>;
    filters: Partial<Filters>;
    statuses: VerticalTrainingStatus[];
    perPageOptions: number[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    const selectableIds = trainings.data.map((training) => training.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));
    const someSelected = selectableIds.some((id) => selectedIds.includes(id));

    useEffect(() => {
        setSelectedIds([]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [trainings.data.map((training) => training.id).join(',')]);

    function toggleSelectAll(checked: boolean) {
        setSelectedIds((current) =>
            checked
                ? [...new Set([...current, ...selectableIds])]
                : current.filter((id) => !selectableIds.includes(id)),
        );
    }

    function toggleSelectTraining(id: number, checked: boolean) {
        setSelectedIds((current) =>
            checked ? [...current, id] : current.filter((i) => i !== id),
        );
    }

    function bulkDeleteTrainings() {
        router.delete(VerticalTrainingController.bulkDestroy.url(), {
            data: { ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
        });
    }

    function applyFilters(next: Partial<Filters>) {
        router.get(
            verticalTrainingIndex().url,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    }

    function handleSearchSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        applyFilters({ search });
    }

    function clearSearch() {
        setSearch('');
        if (filters.search) {
            applyFilters({ search: '' });
        }
    }

    const hasActiveFilters = Boolean(filters.search || filters.status);

    function clearAllFilters() {
        setSearch('');
        applyFilters({ search: '', status: '' });
    }

    function toggleStatus(training: VerticalTraining) {
        router.put(
            VerticalTrainingController.update.url(training.id),
            {
                name: training.name,
                status: training.status === 'active' ? 'inactive' : 'active',
                license_id: training.license_id,
            },
            { preserveScroll: true },
        );
    }

    function destroyTraining(training: VerticalTraining) {
        router.delete(VerticalTrainingController.destroy.url(training.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Vertical Training" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Vertical Training
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Industry-specific training content for agents.
                        </p>
                    </div>
                    <Button asChild className={brandButtonClass}>
                        <Link href={VerticalTrainingController.create()}>
                            <Plus />
                            New training
                        </Link>
                    </Button>
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
                                    placeholder="Search by name…"
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

                {selectedIds.length > 0 && (
                    <div className="flex items-center justify-between gap-4 rounded-lg border border-[#f598ff]/30 bg-[#f598ff]/10 px-4 py-2 text-sm dark:border-[#f598ff]/20 dark:bg-[#f598ff]/10">
                        <span className="font-medium">
                            {selectedIds.length} selected
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelectedIds([])}
                            >
                                Clear
                            </Button>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                    >
                                        <Trash2 />
                                        Delete selected
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Delete {selectedIds.length}{' '}
                                            {selectedIds.length === 1
                                                ? 'training program'
                                                : 'training programs'}
                                            ?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This permanently removes the
                                            selected training programs. This
                                            cannot be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={bulkDeleteTrainings}
                                        >
                                            Delete
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </div>
                )}

                <div className={resourceCardClass}>
                    <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">
                                    <Checkbox
                                        checked={
                                            allSelected
                                                ? true
                                                : someSelected
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleSelectAll(checked === true)
                                        }
                                        disabled={selectableIds.length === 0}
                                        aria-label="Select all vertical training"
                                    />
                                </TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>License</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Script</TableHead>
                                <TableHead>Added</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {trainings.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No vertical training found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {trainings.data.map((training) => (
                                <TableRow
                                    key={training.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(
                                            VerticalTrainingController.show(
                                                training.id,
                                            ).url,
                                        )
                                    }
                                >
                                    <TableCell
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <Checkbox
                                            checked={selectedIds.includes(
                                                training.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleSelectTraining(
                                                    training.id,
                                                    checked === true,
                                                )
                                            }
                                            aria-label={`Select ${training.name}`}
                                        />
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {training.name}
                                    </TableCell>
                                    <TableCell>
                                        {training.license ? (
                                            training.license.name
                                        ) : (
                                            <span className="text-muted-foreground text-sm">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                training.status === 'active'
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {training.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {training.has_script ? (
                                            <span className="inline-flex items-center gap-1 text-sm">
                                                <CircleCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                                                Written
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground text-sm">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {new Date(
                                            training.created_at,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell
                                        className="text-right"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                    <span className="sr-only">
                                                        Open menu
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={VerticalTrainingController.show(
                                                            training.id,
                                                        )}
                                                    >
                                                        <Eye />
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={VerticalTrainingController.edit(
                                                            training.id,
                                                        )}
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        toggleStatus(training)
                                                    }
                                                >
                                                    {training.status ===
                                                    'active' ? (
                                                        <PowerOff />
                                                    ) : (
                                                        <Power />
                                                    )}
                                                    {training.status ===
                                                    'active'
                                                        ? 'Deactivate'
                                                        : 'Activate'}
                                                </DropdownMenuItem>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={(e) =>
                                                                e.preventDefault()
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>
                                                                Delete{' '}
                                                                {training.name}?
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This permanently
                                                                removes the
                                                                training
                                                                program. This
                                                                cannot be
                                                                undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>
                                                                Cancel
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    destroyTraining(
                                                                        training,
                                                                    )
                                                                }
                                                            >
                                                                Delete
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
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
                            {trainings.meta.from ?? 0}–{trainings.meta.to ?? 0}{' '}
                            of {trainings.meta.total}
                        </span>
                    </div>

                    {trainings.meta.last_page > 1 && (
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                {trainings.links.map((link, index) => {
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

                                    if (index === trainings.links.length - 1) {
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

VerticalTrainingIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Vertical Training', href: verticalTrainingIndex() },
    ],
};
