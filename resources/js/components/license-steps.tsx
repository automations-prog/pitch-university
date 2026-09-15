import { Form, router } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import LicenseStepController from '@/actions/App/Http/Controllers/Admin/LicenseStepController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass } from '@/lib/brand-theme';
import { RichTextContent } from '@/components/rich-text-content';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import type { License, LicenseStep } from '@/types';

function StepFormDialog({
    license,
    step,
    trigger,
}: {
    license: License;
    step?: LicenseStep;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [description, setDescription] = useState(step?.description ?? '');
    const isEditing = Boolean(step);

    const form = isEditing
        ? LicenseStepController.update.form([license.id, step!.id])
        : LicenseStepController.store.form([license.id]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogTitle>
                    {isEditing ? 'Edit step' : 'Add step'}
                </DialogTitle>
                <DialogDescription>
                    {isEditing
                        ? 'Update this instruction step.'
                        : 'Add a new instruction step for this license.'}
                </DialogDescription>

                <Form
                    {...form}
                    resetOnSuccess
                    onSuccess={() => {
                        setOpen(false);
                        setDescription(step?.description ?? '');
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={step?.title}
                                    required
                                    autoFocus
                                    placeholder="e.g. Complete pre-licensing course"
                                />
                                {errors.title && (
                                    <p className="text-destructive text-sm">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <input
                                    type="hidden"
                                    name="description"
                                    value={description}
                                />
                                <RichTextEditor
                                    id="description"
                                    value={description}
                                    onChange={setDescription}
                                    placeholder="Details for this step"
                                />
                                {errors.description && (
                                    <p className="text-destructive text-sm">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className={brandButtonClass}
                                >
                                    {processing && <Spinner />}
                                    {isEditing ? 'Save step' : 'Add step'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function LicenseSteps({
    license,
    steps,
}: {
    license: License;
    steps: LicenseStep[];
}) {
    function move(step: LicenseStep, direction: 'up' | 'down') {
        router.patch(
            LicenseStepController.move.url([license.id, step.id]),
            { direction },
            { preserveScroll: true },
        );
    }

    function destroyStep(step: LicenseStep) {
        router.delete(
            LicenseStepController.destroy.url([license.id, step.id]),
            { preserveScroll: true },
        );
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
                <div>
                    <CardTitle>Instruction steps</CardTitle>
                    <CardDescription>
                        Ordered steps for obtaining or renewing this license.
                    </CardDescription>
                </div>
                <StepFormDialog
                    license={license}
                    trigger={
                        <Button type="button" variant="outline" size="sm">
                            <Plus />
                            Add step
                        </Button>
                    }
                />
            </CardHeader>
            <CardContent>
                {steps.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No steps yet.
                    </p>
                ) : (
                    <ol className="space-y-3">
                        {steps.map((step, index) => (
                            <li
                                key={step.id}
                                className="flex items-start gap-3 rounded-lg border p-3"
                            >
                                <span className="bg-muted flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                                    {index + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">
                                        {step.title}
                                    </p>
                                    {step.description && (
                                        <RichTextContent
                                            html={step.description}
                                            className="text-muted-foreground"
                                        />
                                    )}
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={index === 0}
                                        onClick={() => move(step, 'up')}
                                    >
                                        <ArrowUp className="size-4" />
                                        <span className="sr-only">Move up</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={index === steps.length - 1}
                                        onClick={() => move(step, 'down')}
                                    >
                                        <ArrowDown className="size-4" />
                                        <span className="sr-only">
                                            Move down
                                        </span>
                                    </Button>
                                    <StepFormDialog
                                        license={license}
                                        step={step}
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <Pencil className="size-4" />
                                                <span className="sr-only">
                                                    Edit step
                                                </span>
                                            </Button>
                                        }
                                    />
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <Trash2 className="size-4" />
                                                <span className="sr-only">
                                                    Delete step
                                                </span>
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Delete {step.title}?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    This permanently removes the
                                                    step. This cannot be undone.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Cancel
                                                </AlertDialogCancel>
                                                <AlertDialogAction
                                                    onClick={() =>
                                                        destroyStep(step)
                                                    }
                                                >
                                                    Delete
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
