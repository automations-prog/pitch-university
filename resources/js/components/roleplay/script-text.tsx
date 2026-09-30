import { Fragment } from 'react';
import { brandAccentTextClass } from '@/lib/brand-theme';
import { cn } from '@/lib/utils';

/** Stage directions like "(Let them confirm)" and "- AFFIRM". */
const DIRECTION_PATTERN = /(\([^)]*\)|- AFFIRM)/g;

/** Words the script stresses in capitals, e.g. "IS", "JUST", "ENTITLED". */
const EMPHASIS_PATTERN = /(\b[A-Z][A-Z']+\b)/g;

/** Capitalized words that are names, not stress. */
const NOT_EMPHASIS = new Set(['VA', 'DQ', 'DNC', 'NI']);

/**
 * Renders a script line the way the doc reads: stage directions muted, and
 * the stressed capitalized words highlighted so the trainee leans on them.
 */
export function ScriptText({
    text,
    className,
}: {
    text: string;
    className?: string;
}) {
    return (
        <span className={className}>
            {text.split(DIRECTION_PATTERN).map((part, partIndex) =>
                partIndex % 2 === 1 ? (
                    <span
                        key={partIndex}
                        className="text-muted-foreground text-xs italic"
                    >
                        {part}
                    </span>
                ) : (
                    <Fragment key={partIndex}>
                        {part.split(EMPHASIS_PATTERN).map((word, wordIndex) =>
                            wordIndex % 2 === 1 && !NOT_EMPHASIS.has(word) ? (
                                <strong
                                    key={wordIndex}
                                    className={cn(
                                        'font-bold',
                                        brandAccentTextClass,
                                    )}
                                >
                                    {word}
                                </strong>
                            ) : (
                                word
                            ),
                        )}
                    </Fragment>
                ),
            )}
        </span>
    );
}
