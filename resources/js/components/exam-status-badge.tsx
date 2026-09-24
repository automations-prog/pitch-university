import { Badge } from '@/components/ui/badge';
import { Award, Lock } from 'lucide-react';
import type { ExamStatus } from '@/types';

/**
 * The final exam's overall status, used on the track page, the exam page and
 * the admin views.
 */
export function ExamStatusBadge({ status }: { status: ExamStatus }) {
    switch (status) {
        case 'passed':
            return (
                <Badge className="gap-1 border-0 bg-emerald-600 text-white">
                    <Award className="size-3" />
                    Certified
                </Badge>
            );
        case 'locked':
            return (
                <Badge variant="secondary" className="gap-1">
                    <Lock className="size-3" />
                    Locked
                </Badge>
            );
        case 'failed':
            return <Badge variant="destructive">Needs retake</Badge>;
        case 'in_progress':
            return <Badge variant="secondary">In progress</Badge>;
        default:
            return <Badge variant="secondary">Not started</Badge>;
    }
}
