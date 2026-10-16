import { Form, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass, resourceInputClass } from '@/lib/brand-theme';
import { index as teamsIndex } from '@/routes/admin/teams';
import type { TeamFormData, TeamManagerOption } from '@/types';

const NO_MANAGER = 'none';

/**
 * The fields shared by the team create and edit pages: campaign, name,
 * and the one admin who runs the team.
 */
export function TeamForm({
    form,
    team,
    campaigns,
    managers,
    submitLabel,
    submitIcon,
}: {
    form: { action: string; method: 'get' | 'post' };
    team?: TeamFormData;
    campaigns: { id: number; name: string }[];
    managers: TeamManagerOption[];
    submitLabel: string;
    submitIcon: ReactNode;
}) {
    const [campaignId, setCampaignId] = useState(
        team ? String(team.campaign_id) : '',
    );
    const [managerId, setManagerId] = useState(
        team?.manager_id ? String(team.manager_id) : NO_MANAGER,
    );

    return (
        <Form {...form}>
            {({ processing, errors }) => (
                <div className="space-y-4">
                    {errors.manager_id && (
                        <AlertError
                            title="This admin can't run this team."
                            errors={[errors.manager_id]}
                        />
                    )}
                    <Card>
                        <CardHeader>
                            <CardTitle>Team details</CardTitle>
                            <CardDescription>
                                Every team belongs to one campaign.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid items-start gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="campaign_id">Campaign</Label>
                                <input
                                    type="hidden"
                                    name="campaign_id"
                                    value={campaignId}
                                />
                                <Select
                                    value={campaignId}
                                    onValueChange={setCampaignId}
                                >
                                    <SelectTrigger
                                        id="campaign_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a campaign" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                <InputError message={errors.campaign_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={team?.name}
                                    placeholder="e.g. Team Alpha"
                                    className={resourceInputClass}
                                />
                                <InputError message={errors.name} />
                            </div>
                        </CardContent>

                        <Separator />

                        <CardHeader>
                            <CardTitle>Team admin</CardTitle>
                            <CardDescription>
                                The admin sees and manages only this team&apos;s
                                agents. Each admin runs one team.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid items-start gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="manager_id">Admin</Label>
                                <input
                                    type="hidden"
                                    name="manager_id"
                                    value={
                                        managerId === NO_MANAGER
                                            ? ''
                                            : managerId
                                    }
                                />
                                <Select
                                    value={managerId}
                                    onValueChange={setManagerId}
                                >
                                    <SelectTrigger
                                        id="manager_id"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_MANAGER}>
                                            No admin yet
                                        </SelectItem>
                                        {managers.map((manager) => (
                                            <SelectItem
                                                key={manager.id}
                                                value={String(manager.id)}
                                            >
                                                {manager.name}
                                                {manager.team &&
                                                manager.team !== team?.name
                                                    ? ` (runs ${manager.team})`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.manager_id} />
                                {managers.length === 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        No admins yet. Create a user with the
                                        Admin role first.
                                    </p>
                                )}
                            </div>
                            <p className="text-muted-foreground text-xs sm:pt-7">
                                An admin can only run one team. To give this
                                team an admin who runs another one, take them
                                off that team first.
                            </p>
                        </CardContent>

                        <CardFooter className="justify-end gap-3">
                            <Button variant="outline" asChild>
                                <Link href={teamsIndex()}>Cancel</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className={brandButtonClass}
                            >
                                {processing ? <Spinner /> : submitIcon}
                                {submitLabel}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            )}
        </Form>
    );
}
