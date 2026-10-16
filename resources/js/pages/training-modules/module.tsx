import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, ClipboardList, Clock } from 'lucide-react';
import TrainingModuleController from '@/actions/App/Http/Controllers/TrainingModuleController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { brandButtonClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as trainingModulesIndex } from '@/routes/training-modules';
import type { CourseLessonSummary, CourseModule, CourseTrack } from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    lessons: CourseLessonSummary[];
    questionCount: number;
};

export default function TrainingModulesModule({
    track,
    module,
    lessons,
    questionCount,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training Modules', href: trainingModulesIndex() },
            {
                title: track.name,
                href: TrainingModuleController.showTrack(track.slug),
            },
            {
                title: module.title,
                href: TrainingModuleController.showModule([
                    track.slug,
                    module.slug,
                ]),
            },
        ],
    });

    return (
        <>
            <Head title={module.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={TrainingModuleController.showTrack(track.slug)}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to {track.name}
                    </Link>
                    <h2 className="text-xl font-semibold tracking-tight">
                        {module.badge?.icon} {module.title}
                    </h2>
                    {module.summary && (
                        <p className="text-muted-foreground max-w-3xl text-sm">
                            {module.summary}
                        </p>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Lessons</CardTitle>
                            <CardDescription>
                                {lessons.length} lessons · {module.est_minutes}{' '}
                                min total
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <ul className="divide-y border-t">
                                {lessons.map((lesson, index) => (
                                    <li key={lesson.slug}>
                                        <Link
                                            href={TrainingModuleController.showLesson(
                                                [
                                                    track.slug,
                                                    module.slug,
                                                    lesson.slug,
                                                ],
                                            )}
                                            className="hover:bg-muted/50 flex items-center gap-3 px-6 py-4 transition-colors"
                                        >
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">
                                                    {index + 1}. {lesson.title}
                                                </p>
                                                <p className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                                    <Clock className="size-3" />
                                                    {lesson.est_minutes} min
                                                </p>
                                            </div>
                                            <ChevronRight className="text-muted-foreground size-4" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card className="self-start">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ClipboardList className="size-5" />
                                Practice quiz
                            </CardTitle>
                            <CardDescription>
                                {questionCount} questions. Checked here and
                                never saved.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                asChild
                                className={`w-full ${brandButtonClass}`}
                            >
                                <Link
                                    href={TrainingModuleController.showQuiz([
                                        track.slug,
                                        module.slug,
                                    ])}
                                >
                                    Open quiz
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
