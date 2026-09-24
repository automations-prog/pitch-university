import { Head, Link, router, useHttp } from '@inertiajs/react';
import { Eye, MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import type { MouseEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    index as screeningIndex,
    store as screeningStore,
} from '@/routes/admin/screening';
import {
    bulkDestroy as bulkDestroyResponses,
    destroy as destroyResponse,
    show as showResponse,
} from '@/routes/admin/screening-responses';
import type {
    Paginated,
    RealtimeVoiceOption,
    Screening,
    ScreeningResponse,
} from '@/types';

type Filters = {
    per_page: string;
};

export default function ScreeningIndex({
    responses,
    filters,
    perPageOptions,
    voices,
}: {
    responses: Paginated<ScreeningResponse>;
    filters: Partial<Filters>;
    perPageOptions: number[];
    voices: RealtimeVoiceOption[];
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const creatingRef = useRef(false);
    const defaultVoice =
        voices.find((voice) => voice.id === 'verse')?.id ??
        voices[0]?.id ??
        '';
    const {
        data,
        setData,
        post,
        processing,
    } = useHttp<{ voice: string }, { screening: Screening }>({
        voice: defaultVoice,
    });
    const [, copy] = useClipboard();

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const selectableIds = responses.data.map((response) => response.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));
    const someSelected = selectableIds.some((id) => selectedIds.includes(id));

    useEffect(() => {
        setSelectedIds([]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [responses.data.map((response) => response.id).join(',')]);

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
        // Guards against a fast double-click firing two requests before
        // `processing` (React state, updates async) has re-rendered the
        // button as disabled — that raced into two screenings/links being
        // created from a single "Generate link" click.
        if (creatingRef.current) {
            return;
        }
        creatingRef.current = true;

        void post(screeningStore.url(), {
            onSuccess: async (response) => {
                setCreateOpen(false);
                router.reload({ only: ['responses'] });

                const copied = await copy(response.screening.public_url);

                if (copied) {
                    toast.success('Screening link generated and copied.');
                } else {
                    toast.error(
                        'Screening link generated, but could not be copied automatically.',
                    );
                }
            },
            onFinish: () => {
                creatingRef.current = false;
            },
        });
    }

    function applyFilters(next: Partial<Filters>) {
        router.get(
            screeningIndex().url,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Screening" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
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
                        onClick={() => setCreateOpen(true)}
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
                                            selected screening responses. This
                                            cannot be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={bulkDeleteResponses}
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
                                            toggleSelectAll(checked === true)
                                        }
                                        disabled={selectableIds.length === 0}
                                        aria-label="Select all responses"
                                    />
                                </TableHead>
                                <TableHead>Full name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Phone number</TableHead>
                                <TableHead>Submitted</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {responses.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No responses yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {responses.data.map((response) => {
                                return (
                                    <TableRow key={response.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    response.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleSelectResponse(
                                                        response.id,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Select response from ${response.full_name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {response.full_name}
                                        </TableCell>
                                        <TableCell>{response.email}</TableCell>
                                        <TableCell>
                                            {response.phone_number}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(response.created_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
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
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={showResponse(
                                                                response.id,
                                                            )}
                                                        >
                                                            <Eye />
                                                            View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <AlertDialog>
                                                        <AlertDialogTrigger
                                                            asChild
                                                        >
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={(e) =>
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
                                                                    removes this
                                                                    screening
                                                                    response.
                                                                    This cannot
                                                                    be undone.
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

                <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <span>Rows per page</span>
                        <Select
                            value={filters.per_page ?? '10'}
                            onValueChange={(value) =>
                                applyFilters({ per_page: value })
                            }
                        >
                            <SelectTrigger className="w-20">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {perPageOptions.map((option) => (
                                    <SelectItem
                                        key={option}
                                        value={String(option)}
                                    >
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <span>
                            {responses.meta.from ?? 0}–{responses.meta.to ?? 0}{' '}
                            of {responses.meta.total}
                        </span>
                    </div>

                    {responses.meta.last_page > 1 && (
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                {responses.links.map((link, index) => {
                                    const goToPage = (e: MouseEvent) => {
                                        e.preventDefault();
                                        if (link.url) {
                                            router.get(
                                                link.url,
                                                {},
                                                { preserveState: true },
                                            );
                                        }
                                    };

                                    if (link.label === '...') {
                                        return (
                                            <PaginationItem key={index}>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        );
                                    }

                                    const disabledClass = !link.url
                                        ? 'pointer-events-none opacity-50'
                                        : undefined;

                                    if (index === 0) {
                                        return (
                                            <PaginationItem key={index}>
                                                <PaginationPrevious
                                                    href={link.url ?? '#'}
                                                    onClick={goToPage}
                                                    className={disabledClass}
                                                />
                                            </PaginationItem>
                                        );
                                    }

                                    if (index === responses.links.length - 1) {
                                        return (
                                            <PaginationItem key={index}>
                                                <PaginationNext
                                                    href={link.url ?? '#'}
                                                    onClick={goToPage}
                                                    className={disabledClass}
                                                />
                                            </PaginationItem>
                                        );
                                    }

                                    return (
                                        <PaginationItem key={index}>
                                            <PaginationLink
                                                href={link.url ?? '#'}
                                                isActive={link.active}
                                                onClick={goToPage}
                                            >
                                                {link.label}
                                            </PaginationLink>
                                        </PaginationItem>
                                    );
                                })}
                            </PaginationContent>
                        </Pagination>
                    )}
                </div>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New screening</DialogTitle>
                        <DialogDescription>
                            Choose the AI interviewer voice this screening
                            link's calls will use.
                        </DialogDescription>
                    </DialogHeader>
                    <Select
                        value={data.voice}
                        onValueChange={(value) => setData('voice', value)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Select a voice" />
                        </SelectTrigger>
                        <SelectContent>
                            {voices.map((voice) => (
                                <SelectItem key={voice.id} value={voice.id}>
                                    {voice.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <DialogFooter>
                        <Button
                            type="button"
                            className={brandButtonClass}
                            onClick={createScreening}
                            disabled={processing}
                        >
                            Generate link
                        </Button>
                    </DialogFooter>
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
