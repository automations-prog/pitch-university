import { Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { resourceBadgeClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/admin/users';
import { ArrowLeft, Pencil } from 'lucide-react';
import type { User } from '@/types';

export default function ShowUser({ user }: { user: User }) {
    return (
        <>
            <Head title={user.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={usersIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to users
                    </Link>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {user.name}
                            </h2>
                            <Badge className={resourceBadgeClass}>
                                {user.role}
                            </Badge>
                            <Badge
                                variant={
                                    user.status === 'active'
                                        ? 'outline'
                                        : 'destructive'
                                }
                            >
                                {user.status}
                            </Badge>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={UserController.edit(user.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Account details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground text-sm">
                                Email address
                            </p>
                            <p className="text-sm font-medium">{user.email}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                Email verified
                            </p>
                            <p className="text-sm font-medium">
                                {user.email_verified_at
                                    ? new Date(
                                          user.email_verified_at,
                                      ).toLocaleDateString()
                                    : 'Not verified'}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                Created
                            </p>
                            <p className="text-sm font-medium">
                                {new Date(user.created_at).toLocaleDateString()}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Licenses</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {user.licenses && user.licenses.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {user.licenses.map((license) => (
                                    <Badge
                                        key={license.id}
                                        variant={
                                            license.status === 'active'
                                                ? 'outline'
                                                : 'destructive'
                                        }
                                    >
                                        {license.name}
                                    </Badge>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                No licenses assigned yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowUser.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: usersIndex() },
    ],
};
