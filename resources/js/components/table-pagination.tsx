import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { Paginated } from '@/types';

/**
 * Previous / page numbers / next links for a Laravel paginator. Renders
 * nothing when there is only one page.
 */
export function TablePagination<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.meta.last_page <= 1) {
        return null;
    }

    return (
        <Pagination className="mx-0 w-auto justify-end">
            <PaginationContent>
                {paginator.links.map((link, index) => {
                    const goToPage = (event: MouseEvent) => {
                        event.preventDefault();
                        if (link.url) {
                            router.get(link.url, {}, { preserveState: true });
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

                    if (index === paginator.links.length - 1) {
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
    );
}
