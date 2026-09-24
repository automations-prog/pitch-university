import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import TrainingTrackController from '@/actions/App/Http/Controllers/Admin/TrainingTrackController';
import UserTrackController from '@/actions/App/Http/Controllers/Admin/UserTrackController';
import { TablePagination } from '@/components/table-pagination';
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
    resourceBadgeClass,
    resourceCardClass,
    resourceInputClass,
} from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { progress as trainingProgressReport } from '@/routes/admin/training';
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import {
    BarChart3,
    Eye,
    FilterX,
    MoreHorizontal,
    Search,
    X,
} from 'lucide-react';
import type { Paginated, UserRole } from '@/types';

type TrackColumn = {
    id: number;
    slug: string;
    name: string;
    users_count: number;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    track_ids: number[];
};

type Filters = { search: string; role: string; per_page: string };

const ALL_ROLES = 'all';

export default function TrainingTracksIndex({
    tracks,
    users,
    roles,
    filters,
    perPageOptions,
}: {
    tracks: TrackColumn[];
    users: Paginated<UserRow>;
    roles: UserRole[];
    filters: Filters;
    perPageOptions: number[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingToggle, setPendingToggle] = useState<string | null>(null);

    const hasActiveFilters = Boolean(filters.search || filters.role);
    const columnCount = 4 + tracks.length;

    function applyFilters(next: Partial<Filters>) {
        const merged = { ...filters, ...next };

        router.get(
            trainingTracksIndex(),
            {
                ...(merged.search ? { search: merged.search } : {}),
                ...(merged.role ? { role: merged.role } : {}),
                ...(merged.per_page &&
                merged.per_page !== String(perPageOptions[0])
                    ? { per_page: merged.per_page }
                    : {}),
            },
            { preserveState: true, replace: true },
        );
    }

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        applyFilters({ search });
    }

    function clearSearch() {
        setSearch('');

        if (filters.search) {
            applyFilters({ search: '' });
        }
    }

    function clearAllFilters() {
        setSearch('');
        applyFilters({ search: '', role: '' });
    }

    function toggle(user: UserRow, track: TrackColumn, checked: boolean) {
        const key = `${user.id}-${track.id}`;
        const options = {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setPendingToggle(key),
            onFinish: () => setPendingToggle(null),
        };

        if (checked) {
            router.post(
                UserTrackController.store.url([user.id, track.id]),
                {},
                options,
            );
        } else {
            router.delete(
                UserTrackController.destroy.url([user.id, track.id]),
                options,
            );
        }
    }

    return (
        <>
            <Head title="Training Tracks" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Training Tracks
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Click a user to see their progress. Toggle a track
                            on to show it to that user under My Training.
                            Turning it off hides the track but keeps their
                            progress.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={trainingProgressReport()}>
                            <BarChart3 />
                            Progress report
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <form
                            onSubmit={submitSearch}
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
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
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
                                value={filters.role || ALL_ROLES}
                                onValueChange={(role) =>
                                    applyFilters({
                                        role: role === ALL_ROLES ? '' : role,
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
                                    <SelectItem value={ALL_ROLES}>
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
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                {tracks.map((track) => (
                                    <TableHead
                                        key={track.id}
                                        className="text-center"
                                    >
                                        {track.name}
                                        <span className="text-muted-foreground ml-1 font-normal">
                                            ({track.users_count})
                                        </span>
                                    </TableHead>
                                ))}
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {(tracks.length === 0 ||
                                users.data.length === 0) && (
                                <TableRow>
                                    <TableCell
                                        colSpan={columnCount}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        {tracks.length === 0 ? (
                                            <>
                                                No training tracks yet. Add a
                                                track folder under{' '}
                                                <code>
                                                    database/data/tracks
                                                </code>{' '}
                                                and run{' '}
                                                <code>
                                                    php artisan training:import
                                                </code>
                                                .
                                            </>
                                        ) : (
                                            'No users found.'
                                        )}
                                    </TableCell>
                                </TableRow>
                            )}
                            {tracks.length > 0 &&
                                users.data.map((user) => (
                                    <TableRow
                                        key={user.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                TrainingTrackController.show(
                                                    user.id,
                                                ),
                                            )
                                        }
                                    >
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
                                        {tracks.map((track) => {
                                            const key = `${user.id}-${track.id}`;

                                            return (
                                                <TableCell
                                                    key={track.id}
                                                    className="text-center"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <Checkbox
                                                        checked={user.track_ids.includes(
                                                            track.id,
                                                        )}
                                                        disabled={
                                                            pendingToggle ===
                                                            key
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggle(
                                                                user,
                                                                track,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                        aria-label={`${track.name} for ${user.name}`}
                                                    />
                                                </TableCell>
                                            );
                                        })}
                                        <TableCell
                                            className="text-right"
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
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
                                                            href={TrainingTrackController.show(
                                                                user.id,
                                                            )}
                                                        >
                                                            <Eye />
                                                            View progress
                                                        </Link>
                                                    </DropdownMenuItem>
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
                            value={filters.per_page}
                            onValueChange={(per_page) =>
                                applyFilters({ per_page })
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

                    <TablePagination paginator={users} />
                </div>
            </div>
        </>
    );
}

TrainingTracksIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training Tracks', href: trainingTracksIndex() },
    ],
};
