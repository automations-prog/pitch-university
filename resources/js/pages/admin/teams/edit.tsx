import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import { TeamForm } from '@/components/admin/team-form';
import { dashboard } from '@/routes';
import { index as teamsIndex } from '@/routes/admin/teams';
import type { TeamFormData, TeamManagerOption } from '@/types';

export default function EditTeam({
    team,
    campaigns,
    managers,
}: {
    team: TeamFormData;
    campaigns: { id: number; name: string }[];
    managers: TeamManagerOption[];
}) {
    return (
        <>
            <Head title={`Edit ${team.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={teamsIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to teams
                    </Link>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Edit team
                    </h2>
                </div>

                <TeamForm
                    form={TeamController.update.form(team.id)}
                    team={team}
                    campaigns={campaigns}
                    managers={managers}
                    submitLabel="Save changes"
                    submitIcon={<Check />}
                />
            </div>
        </>
    );
}

EditTeam.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Teams', href: teamsIndex() },
        { title: 'Edit team', href: teamsIndex() },
    ],
};
