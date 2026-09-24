import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import TrainingExamController from '@/actions/App/Http/Controllers/TrainingExamController';
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
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import { ArrowLeft } from 'lucide-react';
import type { CourseQuizQuestion } from '@/types';

type Props = {
    track: { slug: string; name: string };
    examTitle: string;
    section: { key: string; title: string; pass_pct: number };
    attemptNumber: number;
    attemptsAllowed: number;
    questions: CourseQuizQuestion[];
};

export default function TrainingExamSection({
    track,
    examTitle,
    section,
    attemptNumber,
    attemptsAllowed,
    questions,
}: Props) {
    const [answers, setAnswers] = useState<Record<string, number>>({});
    const answeredCount = Object.keys(answers).length;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training', href: trainingIndex() },
            {
                title: track.name,
                href: TrainingController.showTrack(track.slug),
            },
            {
                title: examTitle,
                href: TrainingExamController.show(track.slug),
            },
            {
                title: section.title,
                href: TrainingExamController.take([track.slug, section.key]),
            },
        ],
    });

    return (
        <>
            <Head title={`${examTitle}: ${section.title}`} />

            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={TrainingExamController.show(track.slug)}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        {examTitle}
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-2xl font-semibold tracking-tight">
                            {section.title}
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Attempt {attemptNumber} of {attemptsAllowed} ·{' '}
                            {questions.length} questions · {section.pass_pct}%
                            to pass. Your answers are graded when you submit;
                            you can leave and come back to the same questions.
                        </p>
                    </div>
                </div>

                <Form
                    {...TrainingExamController.submit.form([
                        track.slug,
                        section.key,
                    ])}
                    options={{ preserveScroll: false }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {questions.map((question, index) => (
                                <Card key={question.id} className="gap-4">
                                    <CardHeader>
                                        <CardDescription>
                                            Question {index + 1} of{' '}
                                            {questions.length}
                                        </CardDescription>
                                        <CardTitle className="leading-snug">
                                            {question.question}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <ChoiceList
                                            choices={question.choices}
                                            selectedIndex={
                                                answers[question.id] ?? null
                                            }
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
                                            message={
                                                errors[`answers.${question.id}`]
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            ))}

                            <div className="bg-background/95 sticky bottom-0 flex items-center justify-between gap-4 border-t py-4 backdrop-blur">
                                <span className="text-muted-foreground text-sm">
                                    {answeredCount} of {questions.length}{' '}
                                    answered
                                </span>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        answeredCount < questions.length
                                    }
                                    className={brandButtonClass}
                                >
                                    {processing && <Spinner />}
                                    Submit section
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
