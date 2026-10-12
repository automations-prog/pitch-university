import { createContext, use } from 'react';
import type { RoleplayContent, RoleplayVertical } from '@/lib/roleplay-data';

/**
 * The page's parsed script content, shared with every roleplay component
 * so it doesn't have to be threaded through props.
 */
export const RoleplayContentContext = createContext<RoleplayContent | null>(
    null,
);

export function useRoleplayContent(): RoleplayContent {
    const content = use(RoleplayContentContext);

    if (!content) {
        throw new Error(
            'useRoleplayContent must be used inside RoleplayContentContext.',
        );
    }

    return content;
}

/** The vertical the roleplay page is for (Medicare, ACA). */
export const RoleplayVerticalContext = createContext<RoleplayVertical | null>(
    null,
);

export function useRoleplayVertical(): RoleplayVertical {
    const vertical = use(RoleplayVerticalContext);

    if (!vertical) {
        throw new Error(
            'useRoleplayVertical must be used inside RoleplayVerticalContext.',
        );
    }

    return vertical;
}
