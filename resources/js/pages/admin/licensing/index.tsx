import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, MouseEvent, useEffect, useState } from 'react';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
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
import { index as licensingIndex } from '@/routes/admin/licensing';
import { dashboard } from '@/routes';
import {
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
import type { License, LicenseStatus, Paginated } from '@/types';

type Filters = {
    search: string;
    status: string;
    per_page: string;
};

export default function LicensingIndex({
    licenses,
    filters,
    statuses,
    perPageOptions,
}: {
    licenses: Paginated<License>;
    filters: Partial<Filters>;
    statuses: LicenseStatus[];
    perPageOptions: number[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    const selectableIds = licenses.data.map((license) => license.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));
    const someSelected = selectableIds.some((id) => selectedIds.includes(id));

    useEffect(() => {
        setSelectedIds([]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [licenses.data.map((license) => license.id).join(',')]);

    function toggleSelectAll(checked: boolean) {
        setSelectedIds((current) =>
            checked
                ? [...new Set([...current, ...selectableIds])]
                : current.filter((id) => !selectableIds.includes(id)),
        );
    }

    function toggleSelectLicense(id: number, checked: boolean) {
        setSelectedIds((current) =>
            checked ? [...current, id] : current.filter((i) => i !== id),
        );
    }

    function bulkDeleteLicenses() {
        router.delete(LicenseController.bulkDestroy.url(), {
            data: { ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
        });
    }

    function applyFilters(next: Partial<Filters>) {
        router.get(
            licensingIndex().url,
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

    function toggleStatus(license: License) {
        router.put(
            LicenseController.update.url(license.id),
            {
                name: license.name,
                status: license.status === 'active' ? 'inactive' : 'active',
            },
            { preserveScroll: true },
        );
    }

    function destroyLicense(license: License) {
        router.delete(LicenseController.destroy.url(license.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Licensing" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Licensing
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Track agent licenses and credentials.
                        </p>
                    </div>
                    <Button asChild className={brandButtonClass}>
                        <Link href={LicenseController.create()}>
                            <Plus />
                            New license
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
                                                ? 'license'
                                                : 'licenses'}
                                            ?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This permanently removes the
                                            selected licenses. This cannot be
                                            undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={bulkDeleteLicenses}
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
                                        aria-label="Select all licenses"
                                    />
                                </TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Added</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {licenses.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No licenses found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {licenses.data.map((license) => (
                                <TableRow
                                    key={license.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(
                                            LicenseController.show(license.id)
                                                .url,
                                        )
                                    }
                                >
                                    <TableCell
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <Checkbox
                                            checked={selectedIds.includes(
                                                license.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleSelectLicense(
                                                    license.id,
                                                    checked === true,
                                                )
                                            }
                                            aria-label={`Select ${license.name}`}
                                        />
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {license.name}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                license.status === 'active'
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {license.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {new Date(
                                            license.created_at,
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
                                                        href={LicenseController.show(
                                                            license.id,
                                                        )}
                                                    >
                                                        <Eye />
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={LicenseController.edit(
                                                            license.id,
                                                        )}
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        toggleStatus(license)
                                                    }
                                                >
                                                    {license.status ===
                                                    'active' ? (
                                                        <PowerOff />
                                                    ) : (
                                                        <Power />
                                                    )}
                                                    {license.status === 'active'
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
                                                                {license.name}?
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This permanently
                                                                removes the
                                                                license. This
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
                                                                    destroyLicense(
                                                                        license,
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
                            {licenses.meta.from ?? 0}–{licenses.meta.to ?? 0} of{' '}
                            {licenses.meta.total}
                        </span>
                    </div>

                    {licenses.meta.last_page > 1 && (
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                {licenses.links.map((link, index) => {
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

                                    if (index === licenses.links.length - 1) {
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

LicensingIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Licensing', href: licensingIndex() },
    ],
};
