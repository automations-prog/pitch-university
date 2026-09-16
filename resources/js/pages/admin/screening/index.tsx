import { Head, router, useHttp } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Copy,
    Eye,
    MoreHorizontal,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { brandButtonClass, resourceCardClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import {
    index as screeningIndex,
    store as screeningStore,
} from '@/routes/admin/screening';
import {
    bulkDestroy as bulkDestroyResponses,
    destroy as destroyResponse,
} from '@/routes/admin/screening-responses';
import type { Screening, ScreeningResponse } from '@/types';

export default function ScreeningIndex({
    responses,
}: {
    responses: ScreeningResponse[];
}) {
    const [linkUrl, setLinkUrl] = useState<string | null>(null);
    const { post, processing } = useHttp<
        Record<string, never>,
        { screening: Screening }
    >({});
    const [copiedText, copy] = useClipboard();
    const copyIconFor = (value: string) =>
        copiedText === value ? Check : Copy;
    const CreatedLinkIcon = copyIconFor(linkUrl ?? '');

    const [viewingResponse, setViewingResponse] =
        useState<ScreeningResponse | null>(null);

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const selectableIds = responses.map((response) => response.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));
    const someSelected = selectableIds.some((id) => selectedIds.includes(id));

    useEffect(() => {
        setSelectedIds([]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [responses.map((response) => response.id).join(',')]);

    function toggleSelectAll(checked: boolean) {
        setSelectedIds((current) =>
            checked
                ? [...new Set([...current, ...selectableIds])]
                : current.filter((id) => !selectableIds.includes(id)),
        );
    }

    function toggleSelectResponse(id: number, checked: boolean) {
        setSelectedIds((current) =>
            checked ? [...current, id] : current.filter((i) => i !== id),
        );
    }

    function bulkDeleteResponses() {
        router.delete(bulkDestroyResponses.url(), {
            data: { ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
        });
    }

    function deleteResponse(response: ScreeningResponse) {
        router.delete(destroyResponse.url(response.id), {
            preserveScroll: true,
        });
    }

    function createScreening() {
        void post(screeningStore.url(), {
            onSuccess: (data) => {
                setLinkUrl(data.screening.public_url);
                router.reload({ only: ['responses'] });
            },
        });
    }

    return (
        <>
            <Head title="Screening" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {linkUrl === null ? (
                    <>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-0.5">
                                <h2 className="text-xl font-semibold tracking-tight">
                                    Screening
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Generate public links to collect candidate
                                    screenings.
                                </p>
                            </div>
                            <Button
                                type="button"
                                className={brandButtonClass}
                                onClick={createScreening}
                                disabled={processing}
                            >
                                <Plus />
                                New screening
                            </Button>
                        </div>

                        {selectedIds.length > 0 && (
                            <div className="flex items-center justify-between gap-4 rounded-lg border border-[#f598ff]/30 bg-[#f598ff]/10 px-4 py-2 text-sm dark:border-[#f598ff]/20 dark:bg-[#f598ff]/10">
                                <span className="font-medium">
                                    {selectedIds.length} selected
                                </span>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setSelectedIds([])}
                                    >
                                        Clear
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                            >
                                                <Trash2 />
                                                Delete selected
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Delete {selectedIds.length}{' '}
                                                    {selectedIds.length === 1
                                                        ? 'response'
                                                        : 'responses'}
                                                    ?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    This permanently removes the
                                                    selected screening
                                                    responses. This cannot be
                                                    undone.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Cancel
                                                </AlertDialogCancel>
                                                <AlertDialogAction
                                                    onClick={
                                                        bulkDeleteResponses
                                                    }
                                                >
                                                    Delete
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </div>
                        )}

                        <div className={resourceCardClass}>
                            <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={
                                                    allSelected
                                                        ? true
                                                        : someSelected
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleSelectAll(
                                                        checked === true,
                                                    )
                                                }
                                                disabled={
                                                    selectableIds.length === 0
                                                }
                                                aria-label="Select all responses"
                                            />
                                        </TableHead>
                                        <TableHead>Full name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Birthday</TableHead>
                                        <TableHead>Phone number</TableHead>
                                        <TableHead>Submitted</TableHead>
                                        <TableHead>Link</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {responses.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={8}
                                                className="text-muted-foreground py-8 text-center"
                                            >
                                                No responses yet.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {responses.map((response) => {
                                        const Icon = copyIconFor(
                                            response.public_url,
                                        );

                                        return (
                                            <TableRow key={response.id}>
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selectedIds.includes(
                                                            response.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleSelectResponse(
                                                                response.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                        aria-label={`Select response from ${response.full_name}`}
                                                    />
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {response.full_name}
                                                </TableCell>
                                                <TableCell>
                                                    {response.email}
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(
                                                        response.birthday,
                                                    ).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell>
                                                    {response.phone_number}
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(
                                                        response.created_at,
                                                    ).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            copy(
                                                                response.public_url,
                                                            )
                                                        }
                                                    >
                                                        <Icon />
                                                        Copy link
                                                    </Button>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                                <span className="sr-only">
                                                                    Open menu
                                                                </span>
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setViewingResponse(
                                                                        response,
                                                                    )
                                                                }
                                                            >
                                                                <Eye />
                                                                View
                                                            </DropdownMenuItem>
                                                            <AlertDialog>
                                                                <AlertDialogTrigger
                                                                    asChild
                                                                >
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        onSelect={(
                                                                            e,
                                                                        ) =>
                                                                            e.preventDefault()
                                                                        }
                                                                    >
                                                                        <Trash2 />
                                                                        Delete
                                                                    </DropdownMenuItem>
                                                                </AlertDialogTrigger>
                                                                <AlertDialogContent>
                                                                    <AlertDialogHeader>
                                                                        <AlertDialogTitle>
                                                                            Delete
                                                                            response
                                                                            from{' '}
                                                                            {
                                                                                response.full_name
                                                                            }
                                                                            ?
                                                                        </AlertDialogTitle>
                                                                        <AlertDialogDescription>
                                                                            This
                                                                            permanently
                                                                            removes
                                                                            this
                                                                            screening
                                                                            response.
                                                                            This
                                                                            cannot
                                                                            be
                                                                            undone.
                                                                        </AlertDialogDescription>
                                                                    </AlertDialogHeader>
                                                                    <AlertDialogFooter>
                                                                        <AlertDialogCancel>
                                                                            Cancel
                                                                        </AlertDialogCancel>
                                                                        <AlertDialogAction
                                                                            onClick={() =>
                                                                                deleteResponse(
                                                                                    response,
                                                                                )
                                                                            }
                                                                        >
                                                                            Delete
                                                                        </AlertDialogAction>
                                                                    </AlertDialogFooter>
                                                                </AlertDialogContent>
                                                            </AlertDialog>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                ) : (
                    <div className="flex flex-col gap-4">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-fit"
                            onClick={() => setLinkUrl(null)}
                        >
                            <ArrowLeft />
                            Back to screenings
                        </Button>

                        <Card className="max-w-lg">
                            <CardHeader>
                                <CardTitle>Screening link created</CardTitle>
                                <CardDescription>
                                    Share this link with candidates. Anyone with
                                    the link can fill out the screening form
                                    without signing in.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex w-full items-stretch gap-2">
                                    <Input readOnly value={linkUrl} />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() => copy(linkUrl)}
                                    >
                                        <CreatedLinkIcon />
                                        <span className="sr-only">
                                            Copy link
                                        </span>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>

            <Dialog
                open={viewingResponse !== null}
                onOpenChange={(open) => !open && setViewingResponse(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Response details</DialogTitle>
                        <DialogDescription>
                            Submitted{' '}
                            {viewingResponse &&
                                new Date(
                                    viewingResponse.created_at,
                                ).toLocaleString()}
                        </DialogDescription>
                    </DialogHeader>
                    {viewingResponse && (
                        <div className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Full name
                                </Label>
                                <p className="text-sm font-medium">
                                    {viewingResponse.full_name}
                                </p>
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Email
                                </Label>
                                <p className="text-sm font-medium">
                                    {viewingResponse.email}
                                </p>
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Birthday
                                </Label>
                                <p className="text-sm font-medium">
                                    {new Date(
                                        viewingResponse.birthday,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Phone number
                                </Label>
                                <p className="text-sm font-medium">
                                    {viewingResponse.phone_number}
                                </p>
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Submitted via
                                </Label>
                                <Input
                                    readOnly
                                    value={viewingResponse.public_url}
                                />
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

ScreeningIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Screening', href: screeningIndex() },
    ],
};
