import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import UserLicenses from '@/components/user-licenses';
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
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/admin/users';
import { ArrowLeft, Check, Mail, User as UserIcon } from 'lucide-react';
import type { License, User, UserRole, UserStatus } from '@/types';

export default function EditUser({
    user,
    roles,
    statuses,
    licenses,
    availableLicenses,
}: {
    user: User;
    roles: UserRole[];
    statuses: UserStatus[];
    licenses: License[];
    availableLicenses: License[];
}) {
    const [role, setRole] = useState<UserRole>(user.role);
    const [status, setStatus] = useState<UserStatus>(user.status);

    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={usersIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to users
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Edit user
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Update {user.name}&apos;s account.
                        </p>
                    </div>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-3">
                    <Form
                        {...UserController.update.form(user.id)}
                        resetOnSuccess={['password', 'password_confirmation']}
                        className="lg:col-span-2"
                    >
                        {({ processing, errors }) => (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Account details</CardTitle>
                                    <CardDescription>
                                        Basic info used to sign in.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <div className="relative">
                                            <UserIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                            <Input
                                                id="name"
                                                name="name"
                                                defaultValue={user.name}
                                                required
                                                autoFocus
                                                autoComplete="name"
                                                placeholder="Full name"
                                                className={`pl-9 ${resourceInputClass}`}
                                            />
                                        </div>
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email address
                                        </Label>
                                        <div className="relative">
                                            <Mail className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                defaultValue={user.email}
                                                required
                                                autoComplete="email"
                                                placeholder="email@example.com"
                                                className={`pl-9 ${resourceInputClass}`}
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>
                                </CardContent>

                                <Separator />

                                <CardHeader>
                                    <CardTitle>Password</CardTitle>
                                    <CardDescription>
                                        Leave blank to keep the current
                                        password.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            New password
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            placeholder="Leave blank to keep current password"
                                            className={resourceInputClass}
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm new password
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            placeholder="Confirm new password"
                                            className={resourceInputClass}
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                </CardContent>

                                <Separator />

                                <CardHeader>
                                    <CardTitle>Access</CardTitle>
                                    <CardDescription>
                                        Control what this account can do.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid items-start gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role</Label>
                                        <input
                                            type="hidden"
                                            name="role"
                                            value={role}
                                        />
                                        <Select
                                            value={role}
                                            onValueChange={(value) =>
                                                setRole(value as UserRole)
                                            }
                                        >
                                            <SelectTrigger
                                                id="role"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((r) => (
                                                    <SelectItem
                                                        key={r}
                                                        value={r}
                                                    >
                                                        {r}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.role} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <input
                                            type="hidden"
                                            name="status"
                                            value={status}
                                        />
                                        <Select
                                            value={status}
                                            onValueChange={(value) =>
                                                setStatus(value as UserStatus)
                                            }
                                        >
                                            <SelectTrigger
                                                id="status"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statuses.map((s) => (
                                                    <SelectItem
                                                        key={s}
                                                        value={s}
                                                    >
                                                        {s}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>

                                    <p className="text-muted-foreground text-xs">
                                        Admins can manage users, agents can only
                                        access their own account.
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        Inactive users can&apos;t log in.
                                    </p>
                                </CardContent>

                                <CardFooter className="justify-end gap-3">
                                    <Button variant="outline" asChild>
                                        <Link href={usersIndex()}>Cancel</Link>
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className={brandButtonClass}
                                    >
                                        {processing ? <Spinner /> : <Check />}
                                        Save changes
                                    </Button>
                                </CardFooter>
                            </Card>
                        )}
                    </Form>

                    <UserLicenses
                        user={user}
                        assignedLicenses={licenses}
                        availableLicenses={availableLicenses}
                    />
                </div>
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: usersIndex() },
    ],
};
