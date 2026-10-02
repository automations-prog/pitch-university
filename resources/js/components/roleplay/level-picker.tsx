import {
    Check,
    Flame,
    Heart,
    MessageSquareWarning,
    PhoneCall,
    Search,
    Shield,
    Skull,
    Smile,
    Sparkles,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, type KeyboardEvent, type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    brandAccentTextClass,
    brandButtonClass,
    brandGradientClass,
    brandSelectedClass,
    resourceBadgeClass,
    resourceCardClass,
} from '@/lib/brand-theme';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { useRoleplayContent } from '@/lib/roleplay-content';
import {
    OUTCOME_LABELS,
    startingPatience,
    type DifficultyLevel,
    type Level,
    type Outcome,
} from '@/lib/roleplay-data';
import { consumerLineFor } from '@/lib/roleplay-persona';
import { cn } from '@/lib/utils';

/** Each level keeps its own icon; color comes from the brand palette. */
const LEVEL_ICONS: Record<DifficultyLevel, LucideIcon> = {
    1: Smile,
    2: Shield,
    3: Search,
    4: Flame,
    5: Skull,
};

const OUTCOME_COLORS: Record<Outcome, string> = {
    transfer: 'bg-emerald-500',
    dq: 'bg-amber-500',
    dnc: 'bg-destructive',
};

const SAMPLE_LINE_COUNT = 3;

type StartControls = {
    onStart: () => void;
    isStarting: boolean;
    error: string | null;
    practiceWithoutMic: boolean;
    onPracticeWithoutMicChange: (practiceWithoutMic: boolean) => void;
};

export function LevelPicker({
    selectedLevel,
    onSelect,
    ...startControls
}: {
    selectedLevel: DifficultyLevel;
    onSelect: (level: DifficultyLevel) => void;
} & StartControls) {
    const { levels } = useRoleplayContent();
    const level = levels.find(
        (candidate) => candidate.level === selectedLevel,
    )!;

    const selectWithArrowKeys = (event: KeyboardEvent<HTMLDivElement>) => {
        const step =
            event.key === 'ArrowRight' || event.key === 'ArrowDown'
                ? 1
                : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                  ? -1
                  : 0;

        if (step === 0) {
            return;
        }

        event.preventDefault();
        const next = Math.min(
            Math.max(selectedLevel + step, 1),
            levels.length,
        ) as DifficultyLevel;
        onSelect(next);
        event.currentTarget
            .querySelector<HTMLButtonElement>(`[data-level="${next}"]`)
            ?.focus();
    };

    return (
        <div className="flex flex-col gap-6">
            <div
                role="radiogroup"
                aria-label="Difficulty level"
                onKeyDown={selectWithArrowKeys}
                className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
            >
                {levels.map((candidate) => (
                    <LevelCard
                        key={candidate.level}
                        level={candidate}
                        isSelected={candidate.level === selectedLevel}
                        onSelect={() => onSelect(candidate.level)}
                    />
                ))}
            </div>

            <LevelDetails level={level} {...startControls} />
        </div>
    );
}

function LevelCard({
    level,
    isSelected,
    onSelect,
}: {
    level: Level;
    isSelected: boolean;
    onSelect: () => void;
}) {
    const Icon = LEVEL_ICONS[level.level];

    return (
        <button
            type="button"
            role="radio"
            aria-checked={isSelected}
            tabIndex={isSelected ? 0 : -1}
            data-level={level.level}
            onClick={onSelect}
            className={cn(
                'bg-card relative flex flex-col gap-3 border p-4 text-left shadow-xs',
                resourceCardClass,
                'focus-visible:ring-ring/50 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-[3px] focus-visible:outline-none',
                isSelected && brandSelectedClass,
            )}
        >
            {isSelected && (
                <span
                    className={cn(
                        'absolute top-3 right-3 flex size-5 items-center justify-center rounded-full text-white',
                        brandGradientClass,
                    )}
                >
                    <Check className="size-3" />
                </span>
            )}

            <span
                className={cn(
                    'flex size-10 items-center justify-center rounded-lg text-white',
                    brandGradientClass,
                )}
            >
                <Icon className="size-5" />
            </span>

            <span className="flex flex-col gap-0.5">
                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    Level {level.level}
                </span>
                <span className="font-semibold">{level.name}</span>
            </span>

            <IntensityMeter level={level.level} />
        </button>
    );
}

function IntensityMeter({ level }: { level: DifficultyLevel }) {
    const { levels } = useRoleplayContent();
    return (
        <span className="flex gap-1" aria-hidden>
            {levels.map((candidate) => (
                <span
                    key={candidate.level}
                    className={cn(
                        'h-1.5 flex-1 rounded-full',
                        candidate.level <= level
                            ? brandGradientClass
                            : 'bg-muted',
                    )}
                />
            ))}
        </span>
    );
}

function LevelDetails({
    level,
    onStart,
    isStarting,
    error,
    practiceWithoutMic,
    onPracticeWithoutMicChange,
}: {
    level: Level;
} & StartControls) {
    const { objections, quirkMinLevel } = useRoleplayContent();
    const Icon = LEVEL_ICONS[level.level];
    const patience = startingPatience(level.level);
    const hasQuirk = level.level >= quirkMinLevel;

    const unlocked = useMemo(
        () =>
            objections
                .filter((objection) => objection.minLevel <= level.level)
                .slice()
                .sort((a, b) => b.weight - a.weight),
        [level.level, objections],
    );
    const newlyUnlocked = unlocked.filter(
        (objection) => objection.minLevel === level.level,
    );
    const outcomes = (
        Object.entries(level.outcomeMix) as [Outcome, number][]
    ).filter(([, percent]) => percent > 0);

    return (
        <Card className={cn('py-0', resourceCardClass)}>
            <div className="grid lg:grid-cols-[1fr_24rem]">
                <div className="flex flex-col gap-6 p-6">
                    <div className="flex items-start gap-4">
                        <span
                            className={cn(
                                'flex size-12 shrink-0 items-center justify-center rounded-xl text-white',
                                brandGradientClass,
                            )}
                        >
                            <Icon className="size-6" />
                        </span>
                        <div className="flex flex-col gap-1">
                            <p
                                className={cn(
                                    'text-sm font-medium',
                                    brandAccentTextClass,
                                )}
                            >
                                Level {level.level}
                            </p>
                            <h3 className="text-2xl font-semibold tracking-tight">
                                {level.name}
                            </h3>
                            <p className="text-muted-foreground first-letter:uppercase">
                                {level.temperament}
                            </p>
                        </div>
                    </div>

                    <dl className="grid gap-3 sm:grid-cols-3">
                        <Stat
                            icon={MessageSquareWarning}
                            label="Objections"
                            value={`${level.objectionRange[0]}–${level.objectionRange[1]}`}
                        />
                        <Stat
                            icon={Heart}
                            label="Patience"
                            value={
                                <span className="flex flex-wrap gap-0.5">
                                    {Array.from(
                                        { length: patience },
                                        (_, index) => (
                                            <Heart
                                                key={index}
                                                className="size-3.5 fill-[#f598ff] text-[#f598ff]"
                                            />
                                        ),
                                    )}
                                </span>
                            }
                        />
                        <Stat
                            icon={Sparkles}
                            label="Quirk"
                            value={hasQuirk ? 'Yes' : 'None'}
                        />
                    </dl>

                    <div className="flex flex-col gap-2">
                        <p className="text-sm font-medium">
                            How these calls should end
                        </p>
                        <div className="bg-muted flex h-2.5 overflow-hidden rounded-full">
                            {outcomes.map(([outcome, percent]) => (
                                <span
                                    key={outcome}
                                    className={OUTCOME_COLORS[outcome]}
                                    style={{ width: `${percent}%` }}
                                />
                            ))}
                        </div>
                        <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            {outcomes.map(([outcome, percent]) => (
                                <span
                                    key={outcome}
                                    className="flex items-center gap-1.5"
                                >
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            OUTCOME_COLORS[outcome],
                                        )}
                                    />
                                    {OUTCOME_LABELS[outcome]} {percent}%
                                </span>
                            ))}
                        </div>
                    </div>

                    <div className="mt-auto flex flex-wrap items-center justify-between gap-4 border-t pt-6">
                        <p className="text-muted-foreground max-w-md text-sm">
                            {level.outcomeMix.transfer === 100
                                ? 'Every consumer at this level can be transferred, so focus on the flow.'
                                : 'Not every consumer here should be transferred. Catch DQs and DNC demands.'}
                        </p>
                        <div className="flex flex-col items-end gap-2">
                            <Button
                                size="lg"
                                className={brandButtonClass}
                                disabled={isStarting}
                                onClick={onStart}
                            >
                                {isStarting ? <Spinner /> : <PhoneCall />}
                                Start level {level.level}
                            </Button>
                            <label
                                htmlFor="practice-without-mic"
                                className="text-muted-foreground flex items-center gap-2 text-xs"
                            >
                                <Checkbox
                                    id="practice-without-mic"
                                    checked={practiceWithoutMic}
                                    onCheckedChange={(checked) =>
                                        onPracticeWithoutMicChange(
                                            checked === true,
                                        )
                                    }
                                />
                                Practice without mic
                            </label>
                            {error && (
                                <p className="text-destructive text-xs">
                                    {error}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-muted/40 flex flex-col gap-5 border-t p-6 lg:border-t-0 lg:border-l">
                    <div className="flex flex-col gap-2">
                        <p className="text-sm font-medium">
                            {unlocked.length} objections in play
                        </p>
                        {newlyUnlocked.length > 0 ? (
                            <div className="flex flex-col gap-1.5">
                                <p className="text-muted-foreground text-xs">
                                    New at this level
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {newlyUnlocked.map((objection) => (
                                        <Badge
                                            key={objection.id}
                                            className={resourceBadgeClass}
                                        >
                                            {objection.meaning}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-xs">
                                No new objections. They come on harder.
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-2">
                        <p className="text-muted-foreground text-xs">
                            What you might hear
                        </p>
                        {unlocked
                            .slice(0, SAMPLE_LINE_COUNT)
                            .map((objection) => (
                                <blockquote
                                    key={objection.id}
                                    className="bg-background rounded-lg border px-3 py-2 text-sm"
                                >
                                    “{consumerLineFor(objection, level.level)}”
                                </blockquote>
                            ))}
                    </div>
                </div>
            </div>
        </Card>
    );
}

function Stat({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1 rounded-lg border p-3">
            <dt className="text-muted-foreground flex items-center gap-1.5 text-xs">
                <Icon className="size-3.5" />
                {label}
            </dt>
            <dd className="font-semibold">{value}</dd>
        </div>
    );
}
