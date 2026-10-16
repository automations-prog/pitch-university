import { usePage } from '@inertiajs/react';

/**
 * Returns a checker for the signed-in user's permissions, as shared by
 * the server (`auth.permissions`). The server still enforces every one;
 * this only decides what to show.
 */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage().props;

    return (permission: string) => auth.permissions.includes(permission);
}
