import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import {
    index as licensesIndex,
    show as licensesShow,
} from '@/routes/licenses';
import { ArrowRight, Award } from 'lucide-react';
import type { License } from '@/types';

export default function LicensesIndex({ licenses }: { licenses: License[] }) {
    return (
        <>
            <Head title="My Licenses" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        My Licenses
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Licenses assigned to your account.
                    </p>
                </div>

                {licenses.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No licenses have been assigned to you yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {licenses.map((license) => (
                            <Card key={license.id} className="gap-4">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div
                                            className="flex size-10 items-center justify-center rounded-full text-white"
                                            style={{
                                                background:
                                                    'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                                            }}
                                        >
                                            <Award className="size-5" />
                                        </div>
                                        <Badge
                                            variant={
                                                license.status === 'active'
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {license.status}
                                        </Badge>
                                    </div>
                                    <CardTitle>{license.name}</CardTitle>
                                </CardHeader>
                                <CardFooter>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link href={licensesShow(license.id)}>
                                            View steps
                                            <ArrowRight />
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

LicensesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My Licenses', href: licensesIndex() },
    ],
};
