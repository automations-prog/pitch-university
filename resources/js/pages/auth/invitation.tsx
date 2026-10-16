import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { roleLabel, workLocationLabel } from '@/lib/roles';
import { login } from '@/routes';
import type { InvitationStatus, UserRole, WorkLocation } from '@/types';

type Props = {
    invitation: {
        token: string;
        team: string;
        campaign: string;
        role: UserRole;
        status: InvitationStatus;
    };
    workLocations: WorkLocation[];
    passwordRules: string;
};

const UNUSABLE_MESSAGES: Record<Exclude<InvitationStatus, 'active'>, string> = {
    expired: 'This invite link has expired.',
    revoked: 'This invite link has been turned off.',
    used_up: 'This invite link has already been used as many times as allowed.',
};

export default function Invitation({
    invitation,
    workLocations,
    passwordRules,
}: Props) {
    const [workLocation, setWorkLocation] = useState<WorkLocation | ''>('');

    return (
        <>
            <Head title="Join your team" />

            <div className="bg-muted/50 rounded-lg border px-4 py-3 text-center text-sm">
                You&apos;re joining{' '}
                <span className="font-semibold">{invitation.team}</span> on the{' '}
                <span className="font-semibold">{invitation.campaign}</span>{' '}
                campaign as {invitation.role === 'agent' ? 'an' : 'a'}{' '}
                <span className="font-semibold">
                    {roleLabel(invitation.role)}
                </span>
                .
            </div>

            {invitation.status !== 'active' ? (
                <div className="grid gap-4 text-center">
                    <p className="text-sm">
                        {UNUSABLE_MESSAGES[invitation.status]} Ask your team
                        manager for a new one.
                    </p>
                    <TextLink href={login()} className="text-sm">
                        Log in instead
                    </TextLink>
                </div>
            ) : (
                <Form
                    {...InvitationController.store.form(invitation.token)}
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6">
                                <InputError
                                    message={
                                        (errors as Record<string, string>)
                                            .invitation
                                    }
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        autoComplete="name"
                                        name="name"
                                        placeholder="Full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        name="email"
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Where do you work?</Label>
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
                                            setWorkLocation(
                                                value as WorkLocation | '',
                                            )
                                        }
                                        className="w-full"
                                        aria-label="Work location"
                                    >
                                        {workLocations.map((location) => (
                                            <ToggleGroupItem
                                                key={location}
                                                value={location}
                                                className="flex-1"
                                            >
                                                {workLocationLabel(location)}
                                            </ToggleGroupItem>
                                        ))}
                                    </ToggleGroup>
                                    <InputError
                                        message={errors.work_location}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        required
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="Password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        placeholder="Confirm password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Join team
                                </Button>
                            </div>

                            <div className="text-muted-foreground text-center text-sm">
                                Already have an account?{' '}
                                <TextLink href={login()}>Log in</TextLink>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </>
    );
}

Invitation.layout = {
    title: 'Join your team',
    description: 'Create your account to get started',
};
