import { MessageSquareQuote, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ScriptText } from '@/components/roleplay/script-text';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { brandSelectedClass } from '@/lib/brand-theme';
import { REBUTTAL_CLOSER, REBUTTALS, type Rebuttal } from '@/lib/roleplay-data';
import { cn } from '@/lib/utils';

export function RebuttalsPanel({
    disabled,
    isObjectionOpen,
    onRead,
}: {
    disabled: boolean;
    isObjectionOpen: boolean;
    onRead: (rebuttal: Rebuttal, lineIndex: number) => void;
}) {
    const [query, setQuery] = useState('');

    const rebuttals = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return Object.values(REBUTTALS).filter(
            (rebuttal) =>
                needle === '' ||
                rebuttal.title.toLowerCase().includes(needle) ||
                rebuttal.lines.some((line) =>
                    line.toLowerCase().includes(needle),
                ),
        );
    }, [query]);

    return (
        <Card
            className={cn(
                'flex max-h-[44rem] flex-col gap-4 transition-shadow',
                isObjectionOpen && brandSelectedClass,
            )}
        >
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-2">
                    Rebuttals
                    {isObjectionOpen && (
                        <span className="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75" />
                                <span className="relative inline-flex size-2 rounded-full bg-amber-500" />
                            </span>
                            Objection open
                        </span>
                    )}
                </CardTitle>
                <CardDescription>{REBUTTAL_CLOSER}</CardDescription>
                <div className="relative mt-2">
                    <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search what they said…"
                        className="px-8"
                    />
                    {query !== '' && (
                        <button
                            type="button"
                            onClick={() => setQuery('')}
                            aria-label="Clear search"
                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 overflow-y-auto">
                {rebuttals.map((rebuttal) => (
                    <div
                        key={rebuttal.title}
                        className="flex flex-col gap-2 rounded-lg border p-3 text-sm transition-colors hover:border-[#e6cdf7]"
                    >
                        <p className="font-semibold">{rebuttal.title}</p>
                        {rebuttal.lines.map((line, lineIndex) => (
                            <div
                                key={line}
                                className="flex flex-col gap-2 border-t pt-2 first-of-type:border-t-0 first-of-type:pt-0"
                            >
                                <ScriptText
                                    text={line}
                                    className="text-muted-foreground leading-relaxed"
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="self-end"
                                    disabled={disabled}
                                    onClick={() => onRead(rebuttal, lineIndex)}
                                >
                                    <MessageSquareQuote />
                                    Say this
                                </Button>
                            </div>
                        ))}
                    </div>
                ))}

                {rebuttals.length === 0 && (
                    <p className="text-muted-foreground py-6 text-center text-sm">
                        No rebuttals match “{query}”.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
