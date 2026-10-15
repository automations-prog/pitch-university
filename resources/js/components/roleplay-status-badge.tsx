import { CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { CourseRoleplayStatus } from '@/types';

export const ROLEPLAY_STATUS_LABELS: Record<CourseRoleplayStatus, string> = {
    locked: 'Locked',
    not_started: 'Not started',
    failed: 'Not passed yet',
    passed: 'Passed',
    skipped: 'Skipped',
};

/**
 * Where a trainee stands on a module's roleplay call.
 */
export function RoleplayStatusBadge({
    status,
}: {
    status: CourseRoleplayStatus;
}) {
    return (
        <Badge
            variant={status === 'failed' ? 'destructive' : 'outline'}
            className="gap-1"
        >
            {status === 'passed' && (
                <CheckCircle2 className="size-3 text-emerald-600" />
            )}
            {ROLEPLAY_STATUS_LABELS[status]}
        </Badge>
    );
}
