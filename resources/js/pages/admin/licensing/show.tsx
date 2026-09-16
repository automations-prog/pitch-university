import { Head, Link } from '@inertiajs/react';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
import { RichTextContent } from '@/components/rich-text-content';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as licensingIndex } from '@/routes/admin/licensing';
import { ArrowLeft, Pencil } from 'lucide-react';
import type { License, LicenseStep } from '@/types';

export default function ShowLicense({
    license,
    steps,
}: {
    license: License;
    steps: LicenseStep[];
}) {
    return (
        <>
            <Head title={license.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={licensingIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to licensing
                    </Link>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {license.name}
                            </h2>
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
                        <Button asChild variant="outline" size="sm">
                            <Link href={LicenseController.edit(license.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Created{' '}
                        {new Date(license.created_at).toLocaleDateString()}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Instruction steps</CardTitle>
                        <CardDescription>
                            Ordered steps for obtaining or renewing this
                            license.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {steps.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No steps have been added for this license yet.
                            </p>
                        ) : (
                            <ol className="space-y-3">
                                {steps.map((step) => (
                                    <li
                                        key={step.id}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="text-sm font-medium">
                                            {step.title}
                                        </p>
                                        {step.description && (
                                            <RichTextContent
                                                html={step.description}
                                                className="text-muted-foreground mt-1"
                                            />
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowLicense.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Licensing', href: licensingIndex() },
    ],
};
