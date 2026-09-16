import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { RichTextContent } from '@/components/rich-text-content';
import { dashboard } from '@/routes';
import { index as licensesIndex } from '@/routes/licenses';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import type { License, LicenseStep } from '@/types';

export default function LicenseShow({
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
                        href={licensesIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to my licenses
                    </Link>
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
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Instruction steps</CardTitle>
                        <CardDescription>
                            Follow these steps to obtain or renew this license.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {steps.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No steps have been added for this license yet.
                            </p>
                        ) : (
                            <ol className="space-y-3">
                                {steps.map((step) =>
                                    step.description ? (
                                        <Collapsible
                                            key={step.id}
                                            asChild
                                            defaultOpen={false}
                                        >
                                            <li className="rounded-lg border p-3">
                                                <CollapsibleTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="group flex w-full items-center gap-3 text-left"
                                                    >
                                                        <span className="min-w-0 flex-1 text-sm font-medium">
                                                            {step.title}
                                                        </span>
                                                        <ChevronDown className="text-muted-foreground size-4 shrink-0 transition-transform group-data-[state=open]:rotate-180" />
                                                    </button>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent className="pt-2">
                                                    <RichTextContent
                                                        html={step.description}
                                                        className="text-muted-foreground"
                                                    />
                                                </CollapsibleContent>
                                            </li>
                                        </Collapsible>
                                    ) : (
                                        <li
                                            key={step.id}
                                            className="flex items-center gap-3 rounded-lg border p-3"
                                        >
                                            <span className="min-w-0 flex-1 text-sm font-medium">
                                                {step.title}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LicenseShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My Licenses', href: licensesIndex() },
    ],
};
