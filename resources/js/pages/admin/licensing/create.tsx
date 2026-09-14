import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
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
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass, resourceInputClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as licensingIndex } from '@/routes/admin/licensing';
import { ArrowLeft, BadgeCheck, Plus } from 'lucide-react';
import type { LicenseStatus } from '@/types';

export default function CreateLicense({
    statuses,
}: {
    statuses: LicenseStatus[];
}) {
    const [status, setStatus] = useState<LicenseStatus>('active');

    return (
        <>
            <Head title="New license" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={licensingIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to licensing
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            New license
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Add a new license type to track.
                        </p>
                    </div>
                </div>

                <Form {...LicenseController.store.form()}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>License details</CardTitle>
                                <CardDescription>
                                    Name and status for this license.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid items-start gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <div className="relative">
                                        <BadgeCheck className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="e.g. Life Insurance License"
                                            className={`pl-9 ${resourceInputClass}`}
                                        />
                                    </div>
                                    <InputError message={errors.name} />
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
                                            setStatus(value as LicenseStatus)
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
                                                <SelectItem key={s} value={s}>
                                                    {s}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            </CardContent>

                            <CardFooter className="justify-end gap-3">
                                <Button variant="outline" asChild>
                                    <Link href={licensingIndex()}>Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className={brandButtonClass}
                                >
                                    {processing ? <Spinner /> : <Plus />}
                                    Create license
                                </Button>
                            </CardFooter>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateLicense.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Licensing', href: licensingIndex() },
        { title: 'New license', href: LicenseController.create() },
    ],
};
