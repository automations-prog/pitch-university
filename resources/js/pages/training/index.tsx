import { Head } from '@inertiajs/react';
import {
    TrainingTrackCard,
    type TrackWithProgress,
} from '@/components/training-track-card';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';

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
                        {tracks.map((track) => (
                            <TrainingTrackCard key={track.slug} track={track} />
                        ))}
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
