import { Head, Link } from '@inertiajs/react';
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
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import { ArrowRight, Award, CheckCircle2, GraduationCap } from 'lucide-react';
import type { CourseTrack, CourseTrackProgress } from '@/types';

type TrackWithProgress = CourseTrack & { progress: CourseTrackProgress };

export default function TrainingIndex({
    tracks,
}: {
    tracks: TrackWithProgress[];
}) {
    return (
        <>
            <Head title="My Training" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            My Training
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            The training tracks assigned to you.
                        </p>
                    </div>
                </div>

                {tracks.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No training has been assigned to you yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {tracks.map((track) => {
                            const modulesDone =
                                track.progress.total_modules > 0 &&
                                track.progress.passed_modules ===
                                    track.progress.total_modules;
                            const isCertified =
                                track.progress.is_certified === true;
                            const isComplete = track.progress.has_exam
                                ? isCertified
                                : modulesDone;

                            return (
                                <Card
                                    key={track.slug}
                                    className={`gap-4 ${resourceCardClass}`}
                                >
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
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1"
                                                >
                                                    <CheckCircle2 className="size-3 text-emerald-600" />
                                                    Complete
                                                </Badge>
                                            ) : modulesDone ? (
                                                <Badge variant="secondary">
                                                    Final exam next
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    {track.progress
                                                        .passed_modules > 0
                                                        ? 'In progress'
                                                        : 'Not started'}
                                                </Badge>
                                            )}
                                        </div>
                                        <CardTitle>{track.name}</CardTitle>
                                        {track.description && (
                                            <CardDescription>
                                                {track.description}
                                            </CardDescription>
                                        )}
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <p className="text-muted-foreground text-xs">
                                            {track.progress.passed_modules}/
                                            {track.progress.total_modules}{' '}
                                            modules passed
                                        </p>
                                        <CourseProgressBar
                                            value={
                                                track.progress.passed_modules
                                            }
                                            max={track.progress.total_modules}
                                        />
                                    </CardContent>
                                    <CardFooter className="mt-auto">
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full"
                                        >
                                            <Link
                                                href={TrainingController.showTrack(
                                                    track.slug,
                                                )}
                                            >
                                                {isComplete
                                                    ? 'Review track'
                                                    : 'Open track'}
                                                <ArrowRight />
                                            </Link>
                                        </Button>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

TrainingIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training', href: trainingIndex() },
    ],
};
