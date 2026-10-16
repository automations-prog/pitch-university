import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import TrainingTrackController from '@/actions/App/Http/Controllers/Admin/TrainingTrackController';
import UserTrackController from '@/actions/App/Http/Controllers/Admin/UserTrackController';
import { FilterSelect } from '@/components/admin/filter-select';
import { TablePagination } from '@/components/table-pagination';
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
import { resourceCardClass, resourceInputClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import { Eye, FilterX, MoreHorizontal, Search, X } from 'lucide-react';
import { useCan } from '@/hooks/use-can';
import { workLocationLabel } from '@/lib/roles';
import type { Paginated, TeamOption, UserStatus, WorkLocation } from '@/types';

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
    track_ids: number[];
};

type Filters = {
    search: string;
    campaign?: string;
    team?: string;
    work_location?: string;
    status?: string;
    track?: string;
    per_page: string;
};

export default function TrainingTracksIndex({
    tracks,
    users,
    filters,
    perPageOptions,
    teams,
    workLocations,
    statuses,
}: {
    tracks: TrackColumn[];
    users: Paginated<UserRow>;
    filters: Filters;
    perPageOptions: number[];
    teams: TeamOption[];
    workLocations: WorkLocation[];
    statuses: UserStatus[];
}) {
    const can = useCan();
    const canAssignTracks = can('training-tracks.manage');
    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingToggle, setPendingToggle] = useState<string | null>(null);

    const campaigns = [
        ...new Map(
            teams.map((team) => [team.campaign.id, team.campaign]),
        ).values(),
    ].sort((a, b) => a.name.localeCompare(b.name));
    const teamsInCampaign = teams.filter(
        (team) =>
            !filters.campaign || String(team.campaign.id) === filters.campaign,
    );
    const hasActiveFilters = Boolean(
        filters.search ||
        filters.campaign ||
        filters.team ||
        filters.work_location ||
        filters.status ||
        filters.track,
    );
    const columnCount = 3 + tracks.length;

    function applyFilters(next: Partial<Filters>) {
        const merged = { ...filters, ...next };

        router.get(
            trainingTracksIndex(),
            {
                ...Object.fromEntries(
                    (
                        [
                            'search',
                            'campaign',
                            'team',
                            'work_location',
                            'status',
                            'track',
                        ] as const
                    )
                        .filter((key) => merged[key])
                        .map((key) => [key, merged[key]]),
                ),
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
        applyFilters({
            search: '',
            campaign: '',
            team: '',
            work_location: '',
            status: '',
            track: '',
        });
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

                        {campaigns.length > 1 && (
                            <FilterSelect
                                id="campaign-filter"
                                label="Campaign"
                                allLabel="All campaigns"
                                value={filters.campaign}
                                options={campaigns.map((campaign) => ({
                                    value: String(campaign.id),
                                    label: campaign.name,
                                }))}
                                onChange={(campaign) =>
                                    applyFilters({ campaign, team: '' })
                                }
                            />
                        )}

                        {teams.length > 1 && (
                            <FilterSelect
                                id="team-filter"
                                label="Team"
                                allLabel="All teams"
                                value={filters.team}
                                options={teamsInCampaign.map((team) => ({
                                    value: String(team.id),
                                    label: team.name,
                                }))}
                                onChange={(team) => applyFilters({ team })}
                            />
                        )}

                        <FilterSelect
                            id="track-filter"
                            label="Track"
                            allLabel="Any track"
                            value={filters.track}
                            options={[
                                { value: 'none', label: 'No track assigned' },
                                ...tracks.map((track) => ({
                                    value: String(track.id),
                                    label: track.name,
                                })),
                            ]}
                            onChange={(track) => applyFilters({ track })}
                        />

                        <FilterSelect
                            id="location-filter"
                            label="Location"
                            allLabel="All locations"
                            value={filters.work_location}
                            options={workLocations.map((location) => ({
                                value: location,
                                label: workLocationLabel(location),
                            }))}
                            onChange={(location) =>
                                applyFilters({ work_location: location })
                            }
                            className="w-full sm:w-36"
                        />

                        <FilterSelect
                            id="status-filter"
                            label="Status"
                            allLabel="All statuses"
                            value={filters.status}
                            options={statuses.map((status) => ({
                                value: status,
                                label:
                                    status.charAt(0).toUpperCase() +
                                    status.slice(1),
                            }))}
                            onChange={(status) => applyFilters({ status })}
                            className="w-full sm:w-36"
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
                                <TableHead>Email</TableHead>
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
                                                            !canAssignTracks ||
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
