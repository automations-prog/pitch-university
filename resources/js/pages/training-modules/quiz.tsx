import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import TrainingModuleController from '@/actions/App/Http/Controllers/TrainingModuleController';
import { ChoiceList } from '@/components/training-blocks';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { brandButtonClass } from '@/lib/brand-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as trainingModulesIndex } from '@/routes/training-modules';
import type {
    CourseModule,
    CoursePracticeQuestion,
    CourseTrack,
} from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    passPercentage: number;
    questions: CoursePracticeQuestion[];
};

/**
 * The module's quiz as practice: answers are checked in the browser and
 * nothing is sent back, so no attempt is recorded.
 */
export default function TrainingModulesQuiz({
    track,
    module,
    passPercentage,
    questions,
}: Props) {
    const [answers, setAnswers] = useState<Record<string, number>>({});
    const [isChecked, setIsChecked] = useState(false);
    const moduleHref = TrainingModuleController.showModule([
        track.slug,
        module.slug,
    ]);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training Modules', href: trainingModulesIndex() },
            {
                title: track.name,
                href: TrainingModuleController.showTrack(track.slug),
            },
            { title: module.title, href: moduleHref },
            {
                title: 'Practice quiz',
                href: TrainingModuleController.showQuiz([
                    track.slug,
                    module.slug,
                ]),
            },
        ],
    });

    const answeredCount = Object.keys(answers).length;
    const correctCount = questions.filter(
        (question) => answers[question.id] === question.answer_index,
    ).length;
    const scorePercentage =
        questions.length === 0
            ? 0
            : Math.round((correctCount / questions.length) * 100);
    const isPassing = scorePercentage >= passPercentage;

    const retake = () => {
        setAnswers({});
        setIsChecked(false);
        window.scrollTo({ top: 0 });
    };

    return (
        <>
            <Head title={`${module.title} Practice Quiz`} />

            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={moduleHref}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        {module.title}
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-2xl font-semibold tracking-tight">
                            Practice quiz
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {questions.length} questions · {passPercentage}% to
                            pass. Practice only: your answers aren't saved.
                        </p>
                    </div>
                </div>

                {isChecked && (
                    <Card
                        className={cn(
                            'border-2',
                            isPassing
                                ? 'border-emerald-500/60'
                                : 'border-red-500/60',
                        )}
                    >
                        <CardContent className="flex flex-col items-center gap-3 py-4 text-center">
                            <p className="text-4xl font-bold">
                                {scorePercentage}%
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {correctCount} of {questions.length} correct ·{' '}
                                {passPercentage}% needed to pass
                            </p>
                            <Button variant="outline" onClick={retake}>
                                <RotateCcw />
                                Try again
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {questions.map((question, index) => {
                    const selectedIndex = answers[question.id] ?? null;
                    const isCorrect = selectedIndex === question.answer_index;

                    return (
                        <Card key={question.id} className="gap-4">
                            <CardHeader>
                                <CardDescription>
                                    Question {index + 1} of {questions.length}
                                    {isChecked &&
                                        ` · ${isCorrect ? 'Correct' : 'Incorrect'}`}
                                </CardDescription>
                                <CardTitle className="leading-snug">
                                    {question.question}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {isChecked ? (
                                    <ChoiceList
                                        choices={question.choices}
                                        selectedIndex={selectedIndex}
                                        correctIndex={question.answer_index}
                                    />
                                ) : (
                                    <ChoiceList
                                        choices={question.choices}
                                        selectedIndex={selectedIndex}
                                        onSelect={(choiceIndex) =>
                                            setAnswers((current) => ({
                                                ...current,
                                                [question.id]: choiceIndex,
                                            }))
                                        }
                                    />
                                )}
                                {isChecked && question.explanation && (
                                    <p className="bg-muted/50 rounded-lg p-3 text-sm">
                                        {question.explanation}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}

                {!isChecked && (
                    <div className="bg-background/95 sticky bottom-0 flex items-center justify-between gap-4 border-t py-4 backdrop-blur">
                        <span className="text-muted-foreground text-sm">
                            {answeredCount} of {questions.length} answered
                        </span>
                        <Button
                            disabled={answeredCount < questions.length}
                            className={brandButtonClass}
                            onClick={() => {
                                setIsChecked(true);
                                window.scrollTo({ top: 0 });
                            }}
                        >
                            Check answers
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
