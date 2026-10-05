import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const tileClasses = {
    amber: 'bg-amber-300 text-black',
    sky: 'bg-sky-300 text-black',
    teal: 'bg-teal-300 text-black',
    violet: 'bg-violet-300 text-black',
} as const;

const cardClass =
    'flex items-center gap-3 rounded-2xl border bg-card p-4 text-left transition-colors hover:border-violet-300/60 hover:bg-accent/40';

/**
 * A shortcut tile at the top of the admin dashboard. Renders a link when
 * given an `href`, otherwise a button (e.g. to switch dashboard tabs).
 */
export function QuickActionCard({
    title,
    description,
    tile,
    color,
    href,
    onClick,
}: {
    title: string;
    description: string;
    tile: ReactNode;
    color: keyof typeof tileClasses;
    href?: NonNullable<InertiaLinkProps['href']>;
    onClick?: () => void;
}) {
    const content = (
        <>
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold',
                    tileClasses[color],
                )}
            >
                {tile}
            </span>
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold">
                    {title}
                </span>
                <span className="text-muted-foreground block truncate text-xs">
                    {description}
                </span>
            </span>
        </>
    );

    if (href) {
        return (
            <Link href={href} prefetch className={cardClass}>
                {content}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} className={cardClass}>
            {content}
        </button>
    );
}
