import { router } from '@inertiajs/react';
import UserLicenseController from '@/actions/App/Http/Controllers/Admin/UserLicenseController';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import type { License, User } from '@/types';

export default function UserLicenses({
    user,
    assignedLicenses,
    availableLicenses,
}: {
    user: User;
    assignedLicenses: License[];
    availableLicenses: License[];
}) {
    const assignedIds = new Set(assignedLicenses.map((license) => license.id));

    function toggle(license: License, checked: boolean) {
        if (checked) {
            router.post(
                UserLicenseController.store.url([user.id, license.id]),
                {},
                { preserveScroll: true },
            );
        } else {
            router.delete(
                UserLicenseController.destroy.url([user.id, license.id]),
                { preserveScroll: true },
            );
        }
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Licenses</CardTitle>
                <CardDescription>
                    Licenses this user currently holds.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {availableLicenses.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No licenses have been created yet.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {availableLicenses.map((license) => (
                            <li
                                key={license.id}
                                className="flex items-center gap-3 rounded-lg border p-3"
                            >
                                <Checkbox
                                    id={`license-${license.id}`}
                                    checked={assignedIds.has(license.id)}
                                    onCheckedChange={(checked) =>
                                        toggle(license, checked === true)
                                    }
                                    aria-label={`Toggle ${license.name}`}
                                />
                                <label
                                    htmlFor={`license-${license.id}`}
                                    className="min-w-0 flex-1 cursor-pointer truncate text-sm font-medium"
                                >
                                    {license.name}
                                </label>
                                <Badge
                                    variant={
                                        license.status === 'active'
                                            ? 'outline'
                                            : 'destructive'
                                    }
                                >
                                    {license.status}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
