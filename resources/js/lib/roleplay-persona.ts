import {
    DQ_TRAPS,
    LEVELS,
    OBJECTIONS,
    QUIRK_MIN_LEVEL,
    QUIRKS,
    startingPatience,
    type DifficultyLevel,
    type DqTrap,
    type Level,
    type Objection,
    type Outcome,
} from '@/lib/roleplay-data';

export type Lead = {
    name: string;
    state: string;
    zip: string;
};

export type Persona = {
    level: Level;
    lead: Lead;
    patience: number;
    objections: Objection[];
    quirk: string | null;
    outcome: Outcome;
    dqTrap: DqTrap | null;
};

/**
 * Mock dialer lead info so the script's (Customer Name), (state) and
 * (zip code) placeholders have something to read back.
 */
const MOCK_LEADS: Lead[] = [
    { name: 'Dorothy Miller', state: 'Florida', zip: '33511' },
    { name: 'Harold Jenkins', state: 'Ohio', zip: '43204' },
    { name: 'Linda Carter', state: 'Texas', zip: '75217' },
    { name: 'Robert Hayes', state: 'Georgia', zip: '30906' },
    { name: 'Barbara Nguyen', state: 'Arizona', zip: '85308' },
    { name: 'James Walker', state: 'North Carolina', zip: '27406' },
    { name: 'Patricia Moore', state: 'Pennsylvania', zip: '19143' },
    { name: 'Charles Robinson', state: 'Michigan', zip: '48219' },
];

function randomInt(min: number, max: number): number {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function pick<T>(items: T[]): T {
    return items[randomInt(0, items.length - 1)];
}

/**
 * Draws `count` distinct items, each draw weighted by `weightOf`.
 */
function weightedSample<T>(
    items: T[],
    count: number,
    weightOf: (item: T) => number,
): T[] {
    const pool = [...items];
    const drawn: T[] = [];

    while (drawn.length < count && pool.length > 0) {
        const total = pool.reduce((sum, item) => sum + weightOf(item), 0);
        let roll = Math.random() * total;
        const index = pool.findIndex((item) => {
            roll -= weightOf(item);

            return roll < 0;
        });
        const chosen = index === -1 ? pool.length - 1 : index;

        drawn.push(pool[chosen]);
        pool.splice(chosen, 1);
    }

    return drawn;
}

/**
 * Picks the phrasing that matches the level — the docs list each
 * objection's lines escalating from level 1 to level 5.
 */
export function consumerLineFor(objection: Objection, level: number): string {
    const lines = objection.consumerLines;
    const index = Math.min(
        Math.floor(((level - 1) / 5) * lines.length),
        lines.length - 1,
    );

    return lines[index];
}

function drawOutcome(level: Level): Outcome {
    const outcomes = Object.entries(level.outcomeMix) as [Outcome, number][];

    return weightedSample(outcomes, 1, ([, percent]) => percent)[0][0];
}

/**
 * Builds a consumer persona following the rules in
 * `ai-roleplay/OBJECTIONS_AND_PERSONAS.md`: objections drawn by weight from
 * those unlocked at the level, a quirk at level 2+, and an outcome drawn from
 * the level's Transfer / DQ / DNC mix (with a DQ trap when it's DQ).
 */
export function generatePersona(levelNumber: DifficultyLevel): Persona {
    const level = LEVELS.find((candidate) => candidate.level === levelNumber)!;
    const unlocked = OBJECTIONS.filter(
        (objection) => objection.minLevel <= levelNumber,
    );
    const [min, max] = level.objectionRange;
    const outcome = drawOutcome(level);

    return {
        level,
        lead: pick(MOCK_LEADS),
        patience: startingPatience(levelNumber),
        objections: weightedSample(
            unlocked,
            randomInt(min, max),
            (objection) => objection.weight,
        ),
        quirk: levelNumber >= QUIRK_MIN_LEVEL ? pick(QUIRKS) : null,
        outcome,
        dqTrap: outcome === 'dq' ? pick(DQ_TRAPS) : null,
    };
}
