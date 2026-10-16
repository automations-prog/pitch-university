import { Form, Head, router } from '@inertiajs/react';
import { Check, Copy, FilterX, Link2, Plus, XCircle } from 'lucide-react';
import { useState } from 'react';
import TeamInvitationController from '@/actions/App/Http/Controllers/Admin/TeamInvitationController';
import { FilterSelect } from '@/components/admin/filter-select';
import { SearchFilter } from '@/components/admin/search-filter';
import { TableFooter } from '@/components/admin/table-footer';
import InputError from '@/components/input-error';
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTableFilters } from '@/hooks/use-table-filters';
import { brandButtonClass, resourceCardClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as invitationsIndex } from '@/routes/admin/invitations';
import type {
    InvitationRow,
    InvitationStatus,
    Paginated,
    TeamOption,
} from '@/types';

type Filters = {
    search: string;
    campaign: string;
    team: string;
    status: string;
    per_page: string;
};

const STATUS_LABELS: Record<InvitationStatus, string> = {
    active: 'Active',
    expired: 'Expired',
    revoked: 'Revoked',
    used_up: 'Used up',
};

const NO_EXPIRY = 'never';

function NewInviteDialog({ teams }: { teams: TeamOption[] }) {
    const [open, setOpen] = useState(false);
    // Admins can only invite to their own team, so it starts selected.
    const [teamId, setTeamId] = useState(
        teams.length === 1 ? String(teams[0].id) : '',
    );
    const [expiresInDays, setExpiresInDays] = useState('7');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    className={brandButtonClass}
                    disabled={teams.length === 0}
                >
                    <Plus />
                    New invite link
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New invite link</DialogTitle>
                <DialogDescription>
                    Anyone with the link can sign up and join the team as an
                    agent.
                </DialogDescription>

                <Form
                    {...TeamInvitationController.store.form()}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="invite-team">Team</Label>
                                <input
                                    type="hidden"
                                    name="team_id"
                                    value={teamId}
                                />
                                <Select
                                    value={teamId}
                                    onValueChange={setTeamId}
                                >
                                    <SelectTrigger
                                        id="invite-team"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a team" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {teams.map((team) => (
                                            <SelectItem
                                                key={team.id}
                                                value={String(team.id)}
                                            >
                                                {team.campaign.name} ·{' '}
                                                {team.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.team_id} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-expiry">
                                        Expires
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="expires_in_days"
                                        value={
                                            expiresInDays === NO_EXPIRY
                                                ? ''
                                                : expiresInDays
                                        }
                                    />
                                    <Select
                                        value={expiresInDays}
                                        onValueChange={setExpiresInDays}
                                    >
                                        <SelectTrigger
                                            id="invite-expiry"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">
                                                In 1 day
                                            </SelectItem>
                                            <SelectItem value="7">
                                                In 7 days
                                            </SelectItem>
                                            <SelectItem value="30">
                                                In 30 days
                                            </SelectItem>
                                            <SelectItem value="90">
                                                In 90 days
                                            </SelectItem>
                                            <SelectItem value={NO_EXPIRY}>
                                                Never
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.expires_in_days}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="invite-max-uses">
                                        Max sign-ups
                                    </Label>
                                    <Input
                                        id="invite-max-uses"
                                        name="max_uses"
                                        type="number"
                                        min={1}
                                        placeholder="No limit"
                                    />
                                    <InputError message={errors.max_uses} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing || !teamId}
                                    className={brandButtonClass}
                                >
                                    {processing ? <Spinner /> : <Link2 />}
                                    Create link
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function InvitationsIndex({
    invitations,
    filters,
    perPageOptions,
    statuses,
    teams,
}: {
    invitations: Paginated<InvitationRow>;
    filters: Partial<Filters>;
    perPageOptions: number[];
    statuses: InvitationStatus[];
    teams: TeamOption[];
}) {
    const [copiedUrl, copy] = useClipboard();
    const applyFilters = useTableFilters<Filters>(
        invitationsIndex().url,
        filters,
    );
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
        filters.search || filters.campaign || filters.team || filters.status,
    );

    function clearAllFilters() {
        applyFilters({
            search: '',
            campaign: '',
            team: '',
            status: '',
        });
    }

    return (
        <>
            <Head title="Invite links" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Invite links
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Share a team&apos;s link; everyone who signs up with
                            it joins that team.
                        </p>
                    </div>
                    <NewInviteDialog teams={teams} />
                </div>

                {teams.length === 0 && (
                    <p className="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                        You don&apos;t lead any teams yet, so you can&apos;t
                        make invite links.
                    </p>
                )}

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
                            onChange={(campaign) =>
                                applyFilters({ campaign, team: '' })
                            }
                        />
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
                        <FilterSelect
                            id="status-filter"
                            label="Status"
                            allLabel="All statuses"
                            value={filters.status}
                            options={statuses.map((status) => ({
                                value: status,
                                label: STATUS_LABELS[status],
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
                                <TableHead>Team</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sign-ups</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead>Created by</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invitations.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No invite links found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {invitations.data.map((invitation) => (
                                <TableRow key={invitation.id}>
                                    <TableCell>
                                        <div className="leading-tight">
                                            <div className="font-medium">
                                                {invitation.team.name}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {invitation.campaign.name}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                invitation.status === 'active'
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {STATUS_LABELS[invitation.status]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {invitation.uses_count}
                                        {invitation.max_uses !== null &&
                                            ` / ${invitation.max_uses}`}
                                    </TableCell>
                                    <TableCell>
                                        {invitation.expires_at
                                            ? new Date(
                                                  invitation.expires_at,
                                              ).toLocaleDateString()
                                            : 'Never'}
                                    </TableCell>
                                    <TableCell>
                                        {invitation.created_by ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {invitation.status === 'active' && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        copy(invitation.url)
                                                    }
                                                >
                                                    {copiedUrl ===
                                                    invitation.url ? (
                                                        <Check />
                                                    ) : (
                                                        <Copy />
                                                    )}
                                                    {copiedUrl ===
                                                    invitation.url
                                                        ? 'Copied'
                                                        : 'Copy link'}
                                                </Button>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <XCircle className="size-4" />
                                                            <span className="sr-only">
                                                                Revoke link
                                                            </span>
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>
                                                                Revoke this
                                                                link?
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                No one else can
                                                                sign up with it.
                                                                Accounts already
                                                                made with it are
                                                                kept.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>
                                                                Cancel
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    router.delete(
                                                                        TeamInvitationController.destroy.url(
                                                                            invitation.id,
                                                                        ),
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                Revoke
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <TableFooter
                    paginator={invitations}
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

InvitationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invite links', href: invitationsIndex() },
    ],
};
