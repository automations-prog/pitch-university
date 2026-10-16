import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowRight, Clock } from 'lucide-react';
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
import type { CourseModule, CourseTrack } from '@/types';

type ModuleWithCounts = CourseModule & {
    lesson_count: number;
    question_count: number;
};

export default function TrainingModulesTrack({
    track,
    modules,
}: {
    track: CourseTrack;
    modules: ModuleWithCounts[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training Modules', href: trainingModulesIndex() },
            {
                title: track.name,
                href: TrainingModuleController.showTrack(track.slug),
            },
        ],
    });

    return (
        <>
            <Head title={`${track.name} Modules`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        {track.name}
                    </h2>
                    {track.description && (
                        <p className="text-muted-foreground max-w-3xl text-sm">
                            {track.description}
                        </p>
                    )}
                </div>

                {modules.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No modules have been published yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {modules.map((module, index) => (
                            <Card
                                key={module.slug}
                                className={cn('gap-4', resourceCardClass)}
                            >
                                <CardHeader>
                                    <div
                                        className="flex size-10 items-center justify-center rounded-full text-lg text-white"
                                        style={{
                                            background:
                                                'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                                        }}
                                    >
                                        {module.badge?.icon ?? index + 1}
                                    </div>
                                    <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                        Module {index + 1}
                                    </p>
                                    <CardTitle>{module.title}</CardTitle>
                                    {module.summary && (
                                        <CardDescription>
                                            {module.summary}
                                        </CardDescription>
                                    )}
                                </CardHeader>
                                <CardContent className="text-muted-foreground flex items-center gap-4 text-xs">
                                    <span className="inline-flex items-center gap-1">
                                        <Clock className="size-3" />
                                        {module.est_minutes} min
                                    </span>
                                    <span>{module.lesson_count} lessons</span>
                                    <span>
                                        {module.question_count} quiz questions
                                    </span>
                                </CardContent>
                                <CardFooter className="mt-auto">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link
                                            href={TrainingModuleController.showModule(
                                                [track.slug, module.slug],
                                            )}
                                        >
                                            Open module
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
