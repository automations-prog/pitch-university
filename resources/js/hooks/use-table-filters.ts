import { router } from '@inertiajs/react';

/**
 * Applies a change to an index page's query filters, keeping the others
 * and replacing the history entry. The page number isn't kept, so any
 * change goes back to the first page.
 */
export function useTableFilters<T extends Record<string, string | undefined>>(
    url: string,
    filters: Partial<T>,
): (next: Partial<T>) => void {
    return (next) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...next }).filter(
                ([, value]) => value !== undefined && value !== '',
            ),
        );

        router.get(url, query, { preserveState: true, replace: true });
    };
}
