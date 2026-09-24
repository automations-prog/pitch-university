import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import InputError from '@/components/input-error';
import { ChoiceList } from '@/components/training-blocks';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass } from '@/lib/brand-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import { ArrowLeft, ArrowRight, RotateCcw } from 'lucide-react';
import type {
    CourseModule,
    CourseTrack,
    CourseQuizAttempt,
    CourseQuizQuestion,
} from '@/types';

type Props = {
    track: CourseTrack;
    module: CourseModule;
    passPercentage: number;
    questions: CourseQuizQuestion[];
    latestAttempt: CourseQuizAttempt | null;
    isPassed: boolean;
    nextModule: { slug: string; title: string } | null;
};

export default function TrainingQuiz({
    track,
    module,
    passPercentage,
    questions,
    latestAttempt,
    isPassed,
    nextModule,
}: Props) {
    const [isRetaking, setIsRetaking] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training', href: trainingIndex() },
            {
                title: track.name,
                href: TrainingController.showTrack(track.slug),
            },
            {
                title: module.title,
                href: TrainingController.showModule([track.slug, module.slug]),
            },
            {
                title: 'Quiz',
                href: TrainingController.showQuiz([track.slug, module.slug]),
            },
        ],
    });

    const showResults = latestAttempt !== null && !isRetaking;

    return (
        <>
            <Head title={`${module.title} Quiz`} />

            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={TrainingController.showModule([
                            track.slug,
                            module.slug,
                        ])}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        {module.title}
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-2xl font-semibold tracking-tight">
                            Module quiz
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {questions.length} questions · You need{' '}
                            {passPercentage}% to pass. You can retake it as many
                            times as you need.
                        </p>
                    </div>
                </div>

                {showResults ? (
                    <QuizResults
                        track={track}
                        module={module}
                        questions={questions}
                        attempt={latestAttempt}
                        passPercentage={passPercentage}
                        isPassed={isPassed}
                        nextModule={nextModule}
                        onRetake={() => setIsRetaking(true)}
                    />
                ) : (
                    <QuizForm
                        track={track}
                        module={module}
                        questions={questions}
                        onSubmitted={() => setIsRetaking(false)}
                    />
                )}
            </div>
        </>
    );
}

function QuizForm({
    track,
    module,
    questions,
    onSubmitted,
}: {
    track: CourseTrack;
    module: CourseModule;
    questions: CourseQuizQuestion[];
    onSubmitted: () => void;
}) {
    const [answers, setAnswers] = useState<Record<string, number>>({});
    const answeredCount = Object.keys(answers).length;

    return (
        <Form
            {...TrainingController.submitQuiz.form([track.slug, module.slug])}
            onSuccess={onSubmitted}
            options={{ preserveScroll: false }}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    {questions.map((question, index) => (
                        <Card key={question.id} className="gap-4">
                            <CardHeader>
                                <CardDescription>
                                    Question {index + 1} of {questions.length}
                                </CardDescription>
                                <CardTitle className="leading-snug">
                                    {question.question}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <ChoiceList
                                    choices={question.choices}
                                    selectedIndex={answers[question.id] ?? null}
                                    onSelect={(choiceIndex) =>
                                        setAnswers((current) => ({
                                            ...current,
                                            [question.id]: choiceIndex,
                                        }))
                                    }
                                />
                                {answers[question.id] !== undefined && (
                                    <input
                                        type="hidden"
                                        name={`answers[${question.id}]`}
                                        value={answers[question.id]}
                                    />
                                )}
                                <InputError
                                    message={errors[`answers.${question.id}`]}
                                />
                            </CardContent>
                        </Card>
                    ))}

                    <InputError message={errors.answers} />

                    <div className="bg-background/95 sticky bottom-0 flex items-center justify-between gap-4 border-t py-4 backdrop-blur">
                        <span className="text-muted-foreground text-sm">
                            {answeredCount} of {questions.length} answered
                        </span>
                        <Button
                            type="submit"
                            disabled={
                                processing || answeredCount < questions.length
                            }
                            className={brandButtonClass}
                        >
                            {processing && <Spinner />}
                            Submit quiz
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function QuizResults({
    track,
    module,
    questions,
    attempt,
    passPercentage,
    isPassed,
    nextModule,
    onRetake,
}: {
    track: CourseTrack;
    module: CourseModule;
    questions: CourseQuizQuestion[];
    attempt: CourseQuizAttempt;
    passPercentage: number;
    isPassed: boolean;
    nextModule: { slug: string; title: string } | null;
    onRetake: () => void;
}) {
    return (
        <div className="space-y-4">
            <Card
                className={cn(
                    'border-2',
                    attempt.passed
                        ? 'border-emerald-500/60'
                        : 'border-red-500/60',
                )}
            >
                <CardContent className="flex flex-col items-center gap-3 py-4 text-center">
                    {attempt.passed && module.badge && (
                        <span className="text-5xl">{module.badge.icon}</span>
                    )}
                    <p className="text-4xl font-bold">{attempt.score_pct}%</p>
                    <p className="text-muted-foreground text-sm">
                        {attempt.correct_count} of {questions.length} correct ·{' '}
                        {passPercentage}% needed to pass
                    </p>
                    <p className="text-lg font-semibold">
                        {attempt.passed
                            ? module.badge
                                ? `You passed and earned the "${module.badge.name}" badge!`
                                : 'You passed!'
                            : 'Not quite. Review the explanations below and try again.'}
                    </p>
                    <div className="flex flex-wrap justify-center gap-3 pt-2">
                        <Button variant="outline" onClick={onRetake}>
                            <RotateCcw />
                            Retake quiz
                        </Button>
                        {isPassed && nextModule && (
                            <Button asChild className={brandButtonClass}>
                                <Link
                                    href={TrainingController.showModule([
                                        track.slug,
                                        nextModule.slug,
                                    ])}
                                >
                                    Next: {nextModule.title}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        )}
                        {isPassed && !nextModule && (
                            <Button asChild className={brandButtonClass}>
                                <Link
                                    href={TrainingController.showTrack(
                                        track.slug,
                                    )}
                                >
                                    Back to {track.name}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {questions.map((question, index) => {
                const result = attempt.results.find(
                    (candidate) => candidate.id === question.id,
                );

                return (
                    <Card key={question.id} className="gap-4">
                        <CardHeader>
                            <CardDescription>
                                Question {index + 1} ·{' '}
                                {result?.is_correct ? 'Correct' : 'Incorrect'}
                            </CardDescription>
                            <CardTitle className="leading-snug">
                                {question.question}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <ChoiceList
                                choices={question.choices}
                                selectedIndex={result?.selected_index ?? null}
                                correctIndex={result?.answer_index ?? null}
                            />
                            {result?.explanation && (
                                <p className="bg-muted/50 rounded-lg p-3 text-sm">
                                    {result.explanation}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
