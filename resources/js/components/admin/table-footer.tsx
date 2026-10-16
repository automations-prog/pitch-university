import { TablePagination } from '@/components/table-pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types';

/**
 * Rows-per-page picker, the "1–10 of 42" range and the page links shown
 * under an index table.
 */
export function TableFooter<T>({
    paginator,
    perPage,
    perPageOptions,
    onPerPageChange,
}: {
    paginator: Paginated<T>;
    perPage: string | undefined;
    perPageOptions: number[];
    onPerPageChange: (perPage: string) => void;
}) {
    return (
        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                <span>Rows per page</span>
                <Select
                    value={perPage ?? String(perPageOptions[0])}
                    onValueChange={onPerPageChange}
                >
                    <SelectTrigger className="w-20">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {perPageOptions.map((option) => (
                            <SelectItem key={option} value={String(option)}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <span>
                    {paginator.meta.from ?? 0}–{paginator.meta.to ?? 0} of{' '}
                    {paginator.meta.total}
                </span>
            </div>

            <TablePagination paginator={paginator} />
        </div>
    );
}
