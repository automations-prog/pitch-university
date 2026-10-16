import { Head, useForm } from '@inertiajs/react';
import { Check, Lock } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass } from '@/lib/brand-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as rolesIndex } from '@/routes/admin/roles';
import type { PermissionOption, RoleMatrixRole } from '@/types';

function RolePermissions({
    role,
    permissions,
}: {
    role: RoleMatrixRole;
    permissions: PermissionOption[];
}) {
    const form = useForm<{ permissions: string[] }>({
        permissions: role.permissions,
    });

    const groups = permissions.reduce<Record<string, PermissionOption[]>>(
        (grouped, permission) => {
            (grouped[permission.group] ??= []).push(permission);

            return grouped;
        },
        {},
    );

    function toggle(name: string, checked: boolean) {
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, name]
                : form.data.permissions.filter(
                      (permission) => permission !== name,
                  ),
        );
    }

    function save() {
        form.put(RoleController.update.url(role.name), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {role.label}
                    {role.locked && (
                        <Lock className="text-muted-foreground size-4" />
                    )}
                </CardTitle>
                <CardDescription>
                    {role.locked
                        ? 'Super admins always hold every permission.'
                        : 'Tick the pages and actions this role can use. Changes apply on the next page load.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                {Object.entries(groups).map(([group, groupPermissions]) => (
                    <fieldset key={group} className="grid content-start gap-2">
                        <legend className="text-muted-foreground mb-1 text-xs font-semibold uppercase">
                            {group}
                        </legend>
                        {groupPermissions.map((permission) => (
                            <label
                                key={permission.name}
                                className={cn(
                                    'flex items-center gap-3 rounded-md px-2 py-1.5 text-sm',
                                    !role.locked &&
                                        'hover:bg-muted/60 cursor-pointer',
                                )}
                            >
                                <Checkbox
                                    checked={
                                        role.locked ||
                                        form.data.permissions.includes(
                                            permission.name,
                                        )
                                    }
                                    disabled={role.locked}
                                    onCheckedChange={(checked) =>
                                        toggle(
                                            permission.name,
                                            checked === true,
                                        )
                                    }
                                />
                                {permission.label}
                            </label>
                        ))}
                    </fieldset>
                ))}
                <InputError
                    message={form.errors.permissions}
                    className="sm:col-span-2 xl:col-span-3"
                />
            </CardContent>
            {!role.locked && (
                <CardFooter className="justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!form.isDirty || form.processing}
                        onClick={() => form.reset()}
                    >
                        Discard
                    </Button>
                    <Button
                        type="button"
                        disabled={!form.isDirty || form.processing}
                        className={brandButtonClass}
                        onClick={save}
                    >
                        {form.processing ? <Spinner /> : <Check />}
                        Save {role.label} permissions
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
}

export default function RolesIndex({
    roles,
    permissions,
}: {
    roles: RoleMatrixRole[];
    permissions: PermissionOption[];
}) {
    const [selectedRole, setSelectedRole] = useState(
        roles.find((role) => !role.locked)?.name ?? roles[0]?.name,
    );
    const role = roles.find((option) => option.name === selectedRole);

    return (
        <>
            <Head title="Roles & Permissions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Roles &amp; Permissions
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Choose which pages each role can open. Admins still
                        only see the agents on their own team.
                    </p>
                </div>

                <div
                    role="tablist"
                    className="bg-card inline-flex w-fit flex-wrap gap-1 rounded-2xl border p-1"
                >
                    {roles.map((option) => (
                        <button
                            key={option.name}
                            type="button"
                            role="tab"
                            aria-selected={option.name === selectedRole}
                            onClick={() => setSelectedRole(option.name)}
                            className={cn(
                                'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                                option.name === selectedRole
                                    ? 'bg-violet-300 text-black'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.label}
                            <span
                                className={cn(
                                    'rounded-full px-1.5 text-[10px] leading-4',
                                    option.name === selectedRole
                                        ? 'bg-black/10'
                                        : 'bg-muted',
                                )}
                            >
                                {option.locked
                                    ? permissions.length
                                    : option.permissions.length}
                            </span>
                        </button>
                    ))}
                </div>

                {role && (
                    <RolePermissions
                        key={`${role.name}-${role.permissions.join(',')}`}
                        role={role}
                        permissions={permissions}
                    />
                )}
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles & Permissions', href: rolesIndex() },
    ],
};
