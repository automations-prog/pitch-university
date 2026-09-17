import { Head, Link } from '@inertiajs/react';
import VerticalTrainingController from '@/actions/App/Http/Controllers/Admin/VerticalTrainingController';
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
import { index as verticalTrainingIndex } from '@/routes/admin/vertical-training';
import { ArrowLeft, Pencil } from 'lucide-react';
import type { VerticalTraining } from '@/types';

export default function ShowVerticalTraining({
    training,
}: {
    training: VerticalTraining;
}) {
    return (
        <>
            <Head title={training.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={verticalTrainingIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to vertical training
                    </Link>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {training.name}
                            </h2>
                            <Badge
                                variant={
                                    training.status === 'active'
                                        ? 'outline'
                                        : 'destructive'
                                }
                            >
                                {training.status}
                            </Badge>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={VerticalTrainingController.edit(
                                    training.id,
                                )}
                            >
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Training details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground text-sm">
                                License
                            </p>
                            <p className="text-sm font-medium">
                                {training.license ? training.license.name : '—'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Roleplay script</CardTitle>
                        <CardDescription>
                            Content the AI uses to run the roleplay call and
                            score the agent.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {training.has_script ? (
                            <div className="space-y-4">
                                <div>
                                    <p className="text-muted-foreground text-sm">
                                        Script title
                                    </p>
                                    <p className="text-sm font-medium">
                                        {training.script_title}
                                    </p>
                                </div>
                                {training.script_scenario && (
                                    <div>
                                        <p className="text-muted-foreground text-sm">
                                            Scenario
                                        </p>
                                        <p className="text-sm whitespace-pre-wrap">
                                            {training.script_scenario}
                                        </p>
                                    </div>
                                )}
                                {training.script_body && (
                                    <div>
                                        <p className="text-muted-foreground text-sm">
                                            Script
                                        </p>
                                        <p className="text-sm whitespace-pre-wrap">
                                            {training.script_body}
                                        </p>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                No script has been written for this training
                                yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowVerticalTraining.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Vertical Training', href: verticalTrainingIndex() },
    ],
};
