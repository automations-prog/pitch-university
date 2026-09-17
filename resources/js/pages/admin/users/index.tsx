import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, MouseEvent, useEffect, useRef, useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    brandButtonClass,
    resourceBadgeClass,
    resourceCardClass,
    resourceInputClass,
} from '@/lib/brand-theme';
import { index as usersIndex } from '@/routes/admin/users';
import { dashboard } from '@/routes';
import {
    Award,
    Eye,
    FilterX,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    PowerOff,
    RefreshCw,
    Search,
    Trash2,
    UserRoundCog,
    X,
} from 'lucide-react';
import type { License, Paginated, User, UserRole, UserStatus } from '@/types';

type PullStatus = {
    finished: boolean;
    totalJobs: number;
    pendingJobs: number;
    processedJobs: number;
    progress: number;
    failedJobs: number;
};

type Filters = {
    search: string;
    role: string;
    status: string;
    per_page: string;
};

export default function UsersIndex({
    users,
    filters,
    roles,
    statuses,
    perPageOptions,
    licenses,
}: {
    users: Paginated<User>;
    filters: Partial<Filters>;
    roles: UserRole[];
    statuses: UserStatus[];
    perPageOptions: number[];
    licenses: License[];
}) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [assignDialogOpen, setAssignDialogOpen] = useState(false);
    const [assignLicenseId, setAssignLicenseId] = useState('');
    const [pulling, setPulling] = useState(false);
    const [pullStatus, setPullStatus] = useState<PullStatus | null>(null);
    const pollTimeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const selectableIds = users.data
        .filter((user) => user.id !== auth.user.id)
        .map((user) => user.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));
    const someSelected = selectableIds.some((id) => selectedIds.includes(id));

    useEffect(() => {
        setSelectedIds([]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [users.data.map((user) => user.id).join(',')]);

    function toggleSelectAll(checked: boolean) {
        setSelectedIds((current) =>
            checked
                ? [...new Set([...current, ...selectableIds])]
                : current.filter((id) => !selectableIds.includes(id)),
        );
    }

    function toggleSelectUser(id: number, checked: boolean) {
        setSelectedIds((current) =>
            checked ? [...current, id] : current.filter((i) => i !== id),
        );
    }

    function bulkDeleteUsers() {
        router.delete(UserController.bulkDestroy.url(), {
            data: { ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
        });
    }

    function bulkAssignLicense() {
        if (!assignLicenseId) {
            return;
        }

        router.post(
            UserController.bulkAssignLicense.url(),
            {
                user_ids: selectedIds,
                license_id: Number(assignLicenseId),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setAssignDialogOpen(false);
                    setAssignLicenseId('');
                },
            },
        );
    }

    function pollPullStatus(progressId: string) {
        fetch(UserController.pullStatus.url(progressId), {
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json())
            .then((status: PullStatus) => {
                setPullStatus(status);

                if (status.finished) {
                    setPulling(false);
                    router.reload({ only: ['users'] });

                    return;
                }

                pollTimeoutRef.current = setTimeout(
                    () => pollPullStatus(progressId),
                    1500,
                );
            })
            .catch(() => {
                setPulling(false);
            });
    }

    function pullAgents() {
        if (pulling) {
            return;
        }

        const progressId = crypto.randomUUID();

        setPulling(true);
        setPullStatus(null);

        router.post(
            UserController.pullAgents.url(),
            { progress_id: progressId },
            {
                preserveScroll: true,
                onSuccess: () => pollPullStatus(progressId),
                onError: () => setPulling(false),
            },
        );
    }

    useEffect(() => {
        return () => {
            if (pollTimeoutRef.current) {
                clearTimeout(pollTimeoutRef.current);
            }
        };
    }, []);

    function applyFilters(next: Partial<Filters>) {
        router.get(
            usersIndex().url,
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

    const hasActiveFilters = Boolean(
        filters.search || filters.role || filters.status,
    );

    function clearAllFilters() {
        setSearch('');
        applyFilters({ search: '', role: '', status: '' });
    }

    function toggleStatus(user: User) {
        router.put(
            UserController.update.url(user.id),
            {
                name: user.name,
                email: user.email,
                role: user.role,
                status: user.status === 'active' ? 'inactive' : 'active',
                password: '',
                password_confirmation: '',
            },
            { preserveScroll: true },
        );
    }

    function destroyUser(user: User) {
        router.delete(UserController.destroy.url(user.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Users
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Manage agent accounts, roles, and access.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={pullAgents}
                            disabled={pulling}
                        >
                            {pulling ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <RefreshCw />
                            )}
                            {pulling ? 'Pulling agents…' : 'Pull agents'}
                        </Button>
                        <Button asChild className={brandButtonClass}>
                            <Link href={UserController.create()}>
                                <Plus />
                                New user
                            </Link>
                        </Button>
                    </div>
                </div>

                {pulling && (
                    <div className="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm">
                        <Loader2 className="text-muted-foreground size-4 animate-spin" />
                        <div className="flex-1">
                            <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                <div
                                    className="bg-primary h-full transition-all"
                                    style={{
                                        width: `${pullStatus?.progress ?? 0}%`,
                                    }}
                                />
                            </div>
                        </div>
                        <span className="text-muted-foreground whitespace-nowrap">
                            {pullStatus
                                ? `${pullStatus.processedJobs}/${pullStatus.totalJobs} batches`
                                : 'Starting…'}
                        </span>
                    </div>
                )}

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
                                htmlFor="role-filter"
                                className="text-muted-foreground text-xs font-semibold uppercase"
                            >
                                Role
                            </Label>
                            <Select
                                value={filters.role || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        role: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="role-filter"
                                    className="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All roles
                                    </SelectItem>
                                    {roles.map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

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
                            <Dialog
                                open={assignDialogOpen}
                                onOpenChange={setAssignDialogOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                    >
                                        <Award />
                                        Assign license
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        Assign license to {selectedIds.length}{' '}
                                        {selectedIds.length === 1
                                            ? 'user'
                                            : 'users'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Choose a license to assign to all
                                        selected users.
                                    </DialogDescription>

                                    <div className="grid gap-2">
                                        <Label htmlFor="bulk-license">
                                            License
                                        </Label>
                                        <Select
                                            value={assignLicenseId}
                                            onValueChange={setAssignLicenseId}
                                        >
                                            <SelectTrigger
                                                id="bulk-license"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select a license" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {licenses.map((license) => (
                                                    <SelectItem
                                                        key={license.id}
                                                        value={String(
                                                            license.id,
                                                        )}
                                                    >
                                                        {license.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="button"
                                            disabled={!assignLicenseId}
                                            className={brandButtonClass}
                                            onClick={bulkAssignLicense}
                                        >
                                            Assign
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
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
                                                ? 'user'
                                                : 'users'}
                                            ?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This permanently removes the
                                            selected user accounts. This cannot
                                            be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={bulkDeleteUsers}
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
                                        aria-label="Select all users"
                                    />
                                </TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Licenses</TableHead>
                                <TableHead>Joined</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {users.data.map((user) => {
                                const isSelf = user.id === auth.user.id;

                                return (
                                    <TableRow
                                        key={user.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                UserController.show(user.id)
                                                    .url,
                                            )
                                        }
                                    >
                                        <TableCell
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    user.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleSelectUser(
                                                        user.id,
                                                        checked === true,
                                                    )
                                                }
                                                disabled={isSelf}
                                                aria-label={`Select ${user.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            <Badge
                                                className={resourceBadgeClass}
                                            >
                                                {user.role}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    user.status === 'active'
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                            >
                                                {user.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {user.licenses &&
                                            user.licenses.length > 0 ? (
                                                <div className="flex flex-wrap items-center gap-1">
                                                    <Badge
                                                        variant={
                                                            user.licenses[0]
                                                                .status ===
                                                            'active'
                                                                ? 'outline'
                                                                : 'destructive'
                                                        }
                                                    >
                                                        {user.licenses[0].name}
                                                    </Badge>
                                                    {user.licenses.length >
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
                                                                    {user.licenses.map(
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
                                            {new Date(
                                                user.created_at,
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
                                                            href={UserController.show(
                                                                user.id,
                                                            )}
                                                        >
                                                            <Eye />
                                                            View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={UserController.edit(
                                                                user.id,
                                                            )}
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            toggleStatus(user)
                                                        }
                                                        disabled={isSelf}
                                                    >
                                                        {user.status ===
                                                        'active' ? (
                                                            <PowerOff />
                                                        ) : (
                                                            <Power />
                                                        )}
                                                        {user.status ===
                                                        'active'
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </DropdownMenuItem>
                                                    {user.role !== 'admin' &&
                                                        !isSelf && (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={UserController.impersonate(
                                                                        user.id,
                                                                    )}
                                                                    method="post"
                                                                    as="button"
                                                                    className="w-full text-left"
                                                                >
                                                                    <UserRoundCog />
                                                                    Impersonate
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        )}
                                                    <AlertDialog>
                                                        <AlertDialogTrigger
                                                            asChild
                                                        >
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                disabled={
                                                                    isSelf
                                                                }
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
                                                                    {user.name}?
                                                                </AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    This
                                                                    permanently
                                                                    removes the
                                                                    user
                                                                    account.
                                                                    This cannot
                                                                    be undone.
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>
                                                                    Cancel
                                                                </AlertDialogCancel>
                                                                <AlertDialogAction
                                                                    onClick={() =>
                                                                        destroyUser(
                                                                            user,
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
                            {users.meta.from ?? 0}–{users.meta.to ?? 0} of{' '}
                            {users.meta.total}
                        </span>
                    </div>

                    {users.meta.last_page > 1 && (
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                {users.links.map((link, index) => {
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

                                    if (index === users.links.length - 1) {
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

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: usersIndex() },
    ],
};
