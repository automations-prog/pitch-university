import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import TrainingController from '@/actions/App/Http/Controllers/TrainingController';
import TrainingExamController from '@/actions/App/Http/Controllers/TrainingExamController';
import { ExamStatusBadge } from '@/components/exam-status-badge';
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
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass } from '@/lib/brand-theme';
import { cn, formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as trainingIndex } from '@/routes/training';
import {
    ArrowLeft,
    Award,
    CheckCircle2,
    ClipboardList,
    RotateCcw,
    ShieldAlert,
} from 'lucide-react';
import type {
    ExamAttemptSummary,
    ExamSectionSummary,
    ExamSummary,
} from '@/types';

type Props = {
    track: { slug: string; name: string };
    exam: ExamSummary;
    baseAttempts: number;
};

export default function TrainingExam({ track, exam, baseAttempts }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Training', href: trainingIndex() },
            {
                title: track.name,
                href: TrainingController.showTrack(track.slug),
            },
            {
                title: exam.title,
                href: TrainingExamController.show(track.slug),
            },
        ],
    });

    return (
        <>
            <Head title={exam.title} />

            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={TrainingController.showTrack(track.slug)}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to {track.name}
                    </Link>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-2xl font-semibold tracking-tight">
                            {exam.title}
                        </h2>
                        <ExamStatusBadge status={exam.status} />
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Pass every section with the required score to get
                        certified. Each section draws a fresh set of questions
                        from a larger pool every attempt. You get {baseAttempts}{' '}
                        attempts per section; if you think a grade was wrong,
                        talk to your manager.
                    </p>
                </div>

                {exam.is_passed && (
                    <Card className="border-2 border-emerald-500/60">
                        <CardContent className="flex flex-col items-center gap-2 py-4 text-center">
                            <Award className="size-10 text-emerald-600" />
                            <p className="text-lg font-semibold">
                                You're certified for {track.name}!
                            </p>
                            <p className="text-muted-foreground text-sm">
                                You passed every section of the final exam.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {exam.sections.map((section) => (
                        <SectionCard
                            key={section.key}
                            track={track}
                            section={section}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function SectionCard({
    track,
    section,
}: {
    track: Props['track'];
    section: ExamSectionSummary;
}) {
    const latest: ExamAttemptSummary | undefined = section.attempts[0];

    return (
        <Card
            className={cn(
                'gap-4',
                section.is_passed && 'border-emerald-500/60',
            )}
        >
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <CardTitle className="flex items-center gap-2">
                        <ClipboardList className="size-5" />
                        {section.title}
                    </CardTitle>
                    {section.is_passed ? (
                        <Badge variant="outline" className="gap-1">
                            <CheckCircle2 className="size-3 text-emerald-600" />
                            Passed
                        </Badge>
                    ) : section.is_out_of_attempts ? (
                        <Badge variant="destructive">Out of attempts</Badge>
                    ) : null}
                </div>
                <CardDescription>
                    {section.question_count} questions · {section.pass_pct}% to
                    pass · attempt{' '}
                    {Math.min(
                        section.attempts_used + (section.is_passed ? 0 : 1),
                        section.attempts_allowed,
                    )}{' '}
                    of {section.attempts_allowed}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {latest ? (
                    <AttemptResult
                        attempt={latest}
                        passPct={section.pass_pct}
                    />
                ) : (
                    <p className="text-muted-foreground text-sm">
                        You haven't taken this section yet.
                    </p>
                )}

                {section.attempts.length > 1 && (
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                            Earlier attempts
                        </p>
                        <ul className="space-y-1 text-sm">
                            {section.attempts.slice(1).map((attempt) => (
                                <li
                                    key={attempt.id}
                                    className="flex items-center gap-2"
                                >
                                    <span className="font-medium">
                                        {attempt.score_pct}%
                                    </span>
                                    <Badge
                                        variant={
                                            attempt.passed
                                                ? 'outline'
                                                : 'destructive'
                                        }
                                    >
                                        {attempt.passed ? 'Passed' : 'Failed'}
                                    </Badge>
                                    <span className="text-muted-foreground">
                                        {formatDate(attempt.submitted_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>

            <CardFooter className="mt-auto">
                {section.is_passed ? null : section.can_start ? (
                    <Form
                        {...TrainingExamController.start.form([
                            track.slug,
                            section.key,
                        ])}
                        className="w-full"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing}
                                className={`w-full ${brandButtonClass}`}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : section.attempts_used > 0 &&
                                  !section.has_open_attempt ? (
                                    <RotateCcw />
                                ) : null}
                                {section.has_open_attempt
                                    ? 'Resume section'
                                    : section.attempts_used > 0
                                      ? 'Retake section'
                                      : 'Start section'}
                            </Button>
                        )}
                    </Form>
                ) : (
                    <p className="text-muted-foreground flex items-start gap-2 text-sm">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        You've used all your attempts. Talk to your manager
                        about a retake.
                    </p>
                )}
            </CardFooter>
        </Card>
    );
}

function AttemptResult({
    attempt,
    passPct,
}: {
    attempt: ExamAttemptSummary;
    passPct: number;
}) {
    const tags = Object.entries(attempt.tag_breakdown ?? {});

    return (
        <div className="space-y-3">
            <div className="flex items-baseline gap-3">
                <span
                    className={cn(
                        'text-3xl font-bold',
                        attempt.passed ? 'text-emerald-600' : 'text-red-600',
                    )}
                >
                    {attempt.score_pct}%
                </span>
                <span className="text-muted-foreground text-sm">
                    {attempt.passed
                        ? 'Passed'
                        : `Below the ${passPct}% pass mark`}{' '}
                    · {formatDate(attempt.submitted_at)}
                </span>
            </div>

            {tags.length > 0 && (
                <div className="space-y-2">
                    <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                        By topic
                    </p>
                    {tags.map(([tag, { correct, total }]) => (
                        <div key={tag} className="space-y-1">
                            <div className="flex justify-between text-sm">
                                <span className="capitalize">{tag}</span>
                                <span className="text-muted-foreground">
                                    {correct}/{total}
                                </span>
                            </div>
                            <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                <div
                                    className="h-full rounded-full bg-[linear-gradient(90deg,#c774ff_0%,#f598ff_100%)]"
                                    style={{
                                        width: `${total > 0 ? (correct / total) * 100 : 0}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
