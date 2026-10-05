import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { show as screeningResponseShow } from '@/routes/admin/screening-responses';
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import type { DashboardTodo, DashboardTodos } from '@/types';

const actionLabels: Record<DashboardTodo['type'], string> = {
    review: 'Review',
    remind: 'Remind',
    start: 'Nudge',
};

/**
 * Ticked items are remembered per browser for the current day only, so
 * anything still outstanding comes back tomorrow.
 */
const storageKey = (): string =>
    `dashboard-todos-done-${new Date().toLocaleDateString('en-CA')}`;

const readDone = (): string[] => {
    try {
        const stored = JSON.parse(localStorage.getItem(storageKey()) ?? '[]');

        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
};

function todoHref(todo: DashboardTodo) {
    return todo.screening_response_id !== null
        ? screeningResponseShow(todo.screening_response_id)
        : trainingTracksIndex();
}

export function TodoList({ todos }: { todos: DashboardTodos }) {
    const [done, setDone] = useState<string[]>(readDone);
    const doneCount = todos.items.filter((todo) =>
        done.includes(todo.key),
    ).length;

    function toggle(key: string, checked: boolean) {
        const next = checked
            ? [...done, key]
            : done.filter((doneKey) => doneKey !== key);

        setDone(next);

        try {
            localStorage.setItem(storageKey(), JSON.stringify(next));
        } catch {
            // Storage unavailable (private mode); keep it for this visit only.
        }
    }

    const percent =
        todos.items.length > 0 ? (doneCount / todos.items.length) * 100 : 0;

    return (
        <div className="bg-card rounded-2xl border p-5">
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-lg font-semibold">Today&apos;s to-do</h3>
                <span className="text-xs font-semibold text-violet-600 dark:text-violet-300">
                    {doneCount} of {todos.items.length} done
                </span>
            </div>
            <div className="bg-muted mt-3 h-1.5 overflow-hidden rounded-full">
                <div
                    className="h-full rounded-full bg-violet-300 transition-all"
                    style={{ width: `${percent}%` }}
                />
            </div>

            {todos.items.length === 0 ? (
                <p className="text-muted-foreground py-10 text-center text-sm">
                    You&apos;re all caught up.
                </p>
            ) : (
                <ul className="mt-4 space-y-2">
                    {todos.items.map((todo) => {
                        const isDone = done.includes(todo.key);

                        return (
                            <li
                                key={todo.key}
                                className="bg-muted/50 flex items-center gap-3 rounded-xl px-4 py-3"
                            >
                                <Checkbox
                                    checked={isDone}
                                    onCheckedChange={(checked) =>
                                        toggle(todo.key, checked === true)
                                    }
                                    aria-label={`Mark "${todo.title}" as done`}
                                    className="size-5 data-[state=checked]:border-violet-300 data-[state=checked]:bg-violet-300 data-[state=checked]:text-black"
                                />
                                <div
                                    className={cn(
                                        'min-w-0 flex-1',
                                        isDone && 'opacity-50',
                                    )}
                                >
                                    <p
                                        className={cn(
                                            'truncate text-sm font-semibold',
                                            isDone && 'line-through',
                                        )}
                                    >
                                        {todo.title}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {todo.description}
                                    </p>
                                </div>
                                <Link
                                    href={todoHref(todo)}
                                    prefetch
                                    className="inline-flex shrink-0 items-center gap-0.5 text-xs font-semibold text-violet-600 underline underline-offset-2 dark:text-violet-300"
                                >
                                    {actionLabels[todo.type]}
                                    <ArrowRight className="size-3" />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}

            {todos.total > todos.items.length && (
                <p className="text-muted-foreground mt-3 text-xs">
                    +{todos.total - todos.items.length} more in the Training and
                    Screening tabs.
                </p>
            )}
        </div>
    );
}
