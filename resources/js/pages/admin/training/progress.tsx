import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { TablePagination } from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { resourceInputClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { progress as trainingProgressReport } from '@/routes/admin/training';
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import { ArrowLeft, Award, Search } from 'lucide-react';
import type { CourseUserProgress, Paginated } from '@/types';

type Props = {
    users: Paginated<CourseUserProgress>;
    tracks: { slug: string; name: string }[];
    modules: { slug: string; title: string }[];
    totalLessons: number;
    examSections: { key: string; title: string }[];
    filters: { track: string | null; search: string };
};

export default function TrainingProgress({
    users,
    tracks,
    modules,
    totalLessons,
    examSections,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilters(next: Partial<Props['filters']>) {
        const merged = { ...filters, ...next };

        router.get(
            trainingProgressReport(),
            {
                ...(merged.track ? { track: merged.track } : {}),
                ...(merged.search ? { search: merged.search } : {}),
            },
            { preserveState: true, replace: true },
        );
    }

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        applyFilters({ search });
    }

    return (
        <>
            <Head title="Training Progress" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={trainingTracksIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to training tracks
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Training Progress
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Lessons completed and best quiz score per module for
                            everyone assigned to, or working on, a track.
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <Select
                        value={filters.track ?? ''}
                        onValueChange={(track) => applyFilters({ track })}
                        disabled={tracks.length === 0}
                    >
                        <SelectTrigger
                            className="sm:w-64"
                            aria-label="Training track"
                        >
                            <SelectValue placeholder="Select a track" />
                        </SelectTrigger>
                        <SelectContent>
                            {tracks.map((track) => (
                                <SelectItem key={track.slug} value={track.slug}>
                                    {track.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <form
                        onSubmit={submitSearch}
                        className="relative w-full max-w-sm"
                    >
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name or email"
                            className={`pl-9 ${resourceInputClass}`}
                        />
                    </form>
                </div>

                {users.data.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            {filters.search
                                ? 'No one matches that search.'
                                : tracks.length === 0
                                  ? 'No training tracks have been imported yet.'
                                  : 'No one is assigned to this track yet.'}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Lessons</TableHead>
                                    {modules.map((module) => (
                                        <TableHead key={module.slug}>
                                            {module.title}
                                        </TableHead>
                                    ))}
                                    {examSections.length > 0 && (
                                        <TableHead>Final Exam</TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {user.email}
                                            </div>
                                            {!user.is_assigned && (
                                                <Badge
                                                    variant="secondary"
                                                    className="mt-1"
                                                >
                                                    Not assigned
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {user.completed_lessons}/
                                            {totalLessons}
                                        </TableCell>
                                        {modules.map((module) => {
                                            const score =
                                                user.modules[module.slug];

                                            return (
                                                <TableCell key={module.slug}>
                                                    {score ? (
                                                        <div className="flex items-center gap-2">
                                                            <Badge
                                                                variant={
                                                                    score.passed
                                                                        ? 'outline'
                                                                        : 'destructive'
                                                                }
                                                            >
                                                                {score.passed
                                                                    ? 'Passed'
                                                                    : 'Not passed'}
                                                            </Badge>
                                                            <span className="text-sm">
                                                                {
                                                                    score.best_score
                                                                }
                                                                %
                                                            </span>
                                                            <span className="text-muted-foreground text-xs">
                                                                (
                                                                {
                                                                    score.attempts_count
                                                                }{' '}
                                                                {score.attempts_count ===
                                                                1
                                                                    ? 'attempt'
                                                                    : 'attempts'}
                                                                )
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>
                                            );
                                        })}
                                        {examSections.length > 0 && (
                                            <TableCell>
                                                <ExamScores
                                                    sections={examSections}
                                                    exam={user.exam}
                                                />
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <TablePagination paginator={users} />
            </div>
        </>
    );
}

function ExamScores({
    sections,
    exam,
}: {
    sections: { key: string; title: string }[];
    exam: CourseUserProgress['exam'];
}) {
    if (!exam) {
        return <span className="text-muted-foreground">—</span>;
    }

    if (exam.is_certified) {
        return (
            <Badge className="gap-1 border-0 bg-emerald-600 text-white">
                <Award className="size-3" />
                Certified
            </Badge>
        );
    }

    const hasAttempts = sections.some((section) => exam.sections[section.key]);

    if (!hasAttempts) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="space-y-1 text-sm">
            {sections.map((section) => {
                const score = exam.sections[section.key];

                return (
                    <div key={section.key} className="flex items-center gap-2">
                        <span className="text-muted-foreground w-24 truncate">
                            {section.title}
                        </span>
                        {score ? (
                            <>
                                <span className="font-medium">
                                    {score.best_score}%
                                </span>
                                <Badge
                                    variant={
                                        score.passed ? 'outline' : 'destructive'
                                    }
                                >
                                    {score.passed ? 'Passed' : 'Not passed'}
                                </Badge>
                            </>
                        ) : (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

TrainingProgress.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training Tracks', href: trainingTracksIndex() },
        { title: 'Progress', href: trainingProgressReport() },
    ],
};
