import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { workLocationLabel } from '@/lib/roles';
import type { TeamOption, WorkLocation } from '@/types';

const NO_TEAM = 'none';
const ALL_CAMPAIGNS = 'all';

/**
 * The onshore/offshore toggle plus campaign and team selects for the user
 * create and edit forms. The campaign only narrows the team list; the
 * user's campaign comes from their team.
 */
export function UserTeamFields({
    teams,
    workLocations,
    defaultTeamId = null,
    defaultWorkLocation = null,
    teamRequired,
    errors,
}: {
    teams: TeamOption[];
    workLocations: WorkLocation[];
    defaultTeamId?: number | null;
    defaultWorkLocation?: WorkLocation | null;
    teamRequired: boolean;
    errors: Partial<Record<'team_id' | 'work_location', string>>;
}) {
    const [workLocation, setWorkLocation] = useState<WorkLocation | ''>(
        defaultWorkLocation ?? '',
    );
    // A user who must pick a team and has only one to choose from (an
    // admin, who can only add people to their own team) starts with it.
    const initialTeamId =
        defaultTeamId ??
        (teamRequired && teams.length === 1 ? teams[0].id : null);
    const [teamId, setTeamId] = useState<string>(
        initialTeamId ? String(initialTeamId) : NO_TEAM,
    );
    const [campaignId, setCampaignId] = useState<string>(() => {
        const team = teams.find((option) => option.id === initialTeamId);

        return team ? String(team.campaign.id) : ALL_CAMPAIGNS;
    });

    const campaigns = useMemo(
        () =>
            [
                ...new Map(
                    teams.map((team) => [team.campaign.id, team.campaign]),
                ).values(),
            ].sort((a, b) => a.name.localeCompare(b.name)),
        [teams],
    );

    const teamsInCampaign = teams.filter(
        (team) =>
            campaignId === ALL_CAMPAIGNS ||
            String(team.campaign.id) === campaignId,
    );

    function selectCampaign(value: string) {
        setCampaignId(value);

        const selectedTeam = teams.find((team) => String(team.id) === teamId);

        if (
            selectedTeam &&
            value !== ALL_CAMPAIGNS &&
            String(selectedTeam.campaign.id) !== value
        ) {
            setTeamId(NO_TEAM);
        }
    }

    return (
        <>
            <div className="grid gap-2 sm:col-span-2">
                <Label>Work location</Label>
                <input
                    type="hidden"
                    name="work_location"
                    value={workLocation}
                />
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={workLocation}
                    onValueChange={(value) =>
                        setWorkLocation(value as WorkLocation | '')
                    }
                    className="w-fit"
                    aria-label="Work location"
                >
                    {workLocations.map((location) => (
                        <ToggleGroupItem
                            key={location}
                            value={location}
                            className="px-4"
                        >
                            {workLocationLabel(location)}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <InputError message={errors.work_location} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="campaign">Campaign</Label>
                <Select value={campaignId} onValueChange={selectCampaign}>
                    <SelectTrigger id="campaign" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_CAMPAIGNS}>
                            All campaigns
                        </SelectItem>
                        {campaigns.map((campaign) => (
                            <SelectItem
                                key={campaign.id}
                                value={String(campaign.id)}
                            >
                                {campaign.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="team_id">Team</Label>
                <input
                    type="hidden"
                    name="team_id"
                    value={teamId === NO_TEAM ? '' : teamId}
                />
                <Select value={teamId} onValueChange={setTeamId}>
                    <SelectTrigger id="team_id" className="w-full">
                        <SelectValue placeholder="Select a team" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_TEAM}>
                            {teamRequired ? 'Select a team' : 'No team'}
                        </SelectItem>
                        {teamsInCampaign.map((team) => (
                            <SelectItem key={team.id} value={String(team.id)}>
                                {campaignId === ALL_CAMPAIGNS
                                    ? `${team.campaign.name} · ${team.name}`
                                    : team.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.team_id} />
            </div>

            {teams.length === 0 && (
                <p className="text-muted-foreground text-xs sm:col-span-2">
                    No teams yet. A super admin can add them on the Teams page.
                </p>
            )}
        </>
    );
}
