import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import VerticalTrainingController from '@/actions/App/Http/Controllers/Admin/VerticalTrainingController';
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
import { Textarea } from '@/components/ui/textarea';
import { brandButtonClass, resourceInputClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as verticalTrainingIndex } from '@/routes/admin/vertical-training';
import { ArrowLeft, Check, Target } from 'lucide-react';
import type { VerticalTraining, VerticalTrainingStatus } from '@/types';

export default function EditVerticalTraining({
    training,
    statuses,
}: {
    training: VerticalTraining;
    statuses: VerticalTrainingStatus[];
}) {
    const [status, setStatus] = useState<VerticalTrainingStatus>(
        training.status,
    );

    return (
        <>
            <Head title={`Edit ${training.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={verticalTrainingIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to vertical training
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Edit training
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Update the {training.name} training program.
                        </p>
                    </div>
                </div>

                <Form {...VerticalTrainingController.update.form(training.id)}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>Training details</CardTitle>
                                <CardDescription>
                                    Name and status for this training program.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid items-start gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <div className="relative">
                                        <Target className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={training.name}
                                            required
                                            autoFocus
                                            placeholder="e.g. Healthcare Vertical"
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
                                            setStatus(
                                                value as VerticalTrainingStatus,
                                            )
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

                            <CardHeader>
                                <CardTitle>Roleplay script</CardTitle>
                                <CardDescription>
                                    Content the AI will use to run the roleplay
                                    call and score the agent. Optional for now.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="script_title">
                                        Script title
                                    </Label>
                                    <Input
                                        id="script_title"
                                        name="script_title"
                                        defaultValue={
                                            training.script_title ?? ''
                                        }
                                        placeholder="e.g. Cold call — initial outreach"
                                        className={resourceInputClass}
                                    />
                                    <InputError message={errors.script_title} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="script_scenario">
                                        Scenario
                                    </Label>
                                    <Textarea
                                        id="script_scenario"
                                        name="script_scenario"
                                        defaultValue={
                                            training.script_scenario ?? ''
                                        }
                                        placeholder="Describe the situation the agent is role-playing."
                                    />
                                    <InputError
                                        message={errors.script_scenario}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="script_body">Script</Label>
                                    <Textarea
                                        id="script_body"
                                        name="script_body"
                                        rows={8}
                                        defaultValue={
                                            training.script_body ?? ''
                                        }
                                        placeholder="Write out the roleplay dialogue or talking points."
                                    />
                                    <InputError message={errors.script_body} />
                                </div>
                            </CardContent>

                            <CardFooter className="justify-end gap-3">
                                <Button variant="outline" asChild>
                                    <Link href={verticalTrainingIndex()}>
                                        Cancel
                                    </Link>
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
            </div>
        </>
    );
}

EditVerticalTraining.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Vertical Training', href: verticalTrainingIndex() },
    ],
};
