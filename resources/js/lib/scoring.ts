import type { TrackStatus } from '@/types';

export function scoreBadgeVariant(
    score: number | null,
): 'outline' | 'secondary' | 'destructive' {
    if (score === null) {
        return 'secondary';
    }

    if (score >= 80) {
        return 'outline';
    }

    if (score >= 60) {
        return 'secondary';
    }

    return 'destructive';
}

/**
 * Display labels for an agent's status in a training track.
 */
export const trackStatusLabels: Record<TrackStatus, string> = {
    certified: 'Certified',
    complete: 'Complete',
    exam_next: 'Final exam next',
    in_progress: 'In progress',
    not_started: 'Not started',
};
