import { Link } from '@inertiajs/react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import { CourseProgressBar } from '@/components/course-progress-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { resourceCardClass } from '@/lib/brand-theme';
import { scoreBadgeVariant } from '@/lib/scoring';
import { ArrowRight, Award, CheckCircle2, GraduationCap } from 'lucide-react';
import type { CourseTrack, CourseTrackProgress } from '@/types';

export type TrackWithProgress = CourseTrack & { progress: CourseTrackProgress };

/**
 * A training track card with the user's status badge, module progress, and a
 * link into the track.
 */
export function TrainingTrackCard({ track }: { track: TrackWithProgress }) {
    const modulesDone =
        track.progress.total_modules > 0 &&
        track.progress.passed_modules === track.progress.total_modules;
    const isCertified = track.progress.is_certified === true;
    const isComplete = track.progress.has_exam ? isCertified : modulesDone;

    return (
        <Card className={`gap-4 ${resourceCardClass}`}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div
                        className="flex size-10 items-center justify-center rounded-full text-white"
                        style={{
                            background:
                                'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                        }}
                    >
                        <GraduationCap className="size-5" />
                    </div>
                    {isCertified ? (
                        <Badge className="gap-1 border-0 bg-emerald-600 text-white">
                            <Award className="size-3" />
                            Certified
                        </Badge>
                    ) : isComplete ? (
                        <Badge variant="outline" className="gap-1">
                            <CheckCircle2 className="size-3 text-emerald-600" />
                            Complete
                        </Badge>
                    ) : modulesDone ? (
                        <Badge variant="secondary">Final exam next</Badge>
                    ) : (
                        <Badge variant="secondary">
                            {track.progress.passed_modules > 0
                                ? 'In progress'
                                : 'Not started'}
                        </Badge>
                    )}
                </div>
                <CardTitle>{track.name}</CardTitle>
                {track.description && (
                    <CardDescription>{track.description}</CardDescription>
                )}
            </CardHeader>
            <CardContent className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-muted-foreground text-xs">
                        {track.progress.passed_modules}/
                        {track.progress.total_modules} modules passed
                    </p>
                    {track.progress.average_score != null && (
                        <Badge
                            variant={scoreBadgeVariant(
                                track.progress.average_score,
                            )}
                        >
                            Avg. {track.progress.average_score}%
                        </Badge>
                    )}
                </div>
                <CourseProgressBar
                    value={track.progress.passed_modules}
                    max={track.progress.total_modules}
                />
            </CardContent>
            <CardFooter className="mt-auto">
                <Button asChild variant="outline" className="w-full">
                    <Link href={TrainingController.showTrack(track.slug)}>
                        {isComplete ? 'Review track' : 'Open track'}
                        <ArrowRight />
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    );
}
