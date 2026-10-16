import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';
import TrainingModuleController from '@/actions/App/Http/Controllers/TrainingModuleController';
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
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as trainingModulesIndex } from '@/routes/training-modules';
import type { CourseTrack } from '@/types';

export default function TrainingModulesIndex({
    tracks,
}: {
    tracks: (CourseTrack & { module_count: number })[];
}) {
    return (
        <>
            <Head title="Training Modules" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Training Modules
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Every training track, with all modules open. Nothing you
                        do here counts toward anyone's training.
                    </p>
                </div>

                {tracks.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No training tracks have been published yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {tracks.map((track) => (
                            <Card
                                key={track.slug}
                                className={cn('gap-4', resourceCardClass)}
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <BookOpen className="size-4" />
                                        {track.name}
                                    </CardTitle>
                                    {track.description && (
                                        <CardDescription>
                                            {track.description}
                                        </CardDescription>
                                    )}
                                </CardHeader>
                                <CardContent className="text-muted-foreground text-xs">
                                    {track.module_count}{' '}
                                    {track.module_count === 1
                                        ? 'module'
                                        : 'modules'}
                                </CardContent>
                                <CardFooter className="mt-auto">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link
                                            href={TrainingModuleController.showTrack(
                                                track.slug,
                                            )}
                                        >
                                            Browse modules
                                            <ArrowRight />
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

TrainingModulesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training Modules', href: trainingModulesIndex() },
    ],
};
