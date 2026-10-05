/**
 * Roleplay content types. The content itself is parsed server-side from
 * `ai-roleplay/medicare_script.md` and `ai-roleplay/OBJECTIONS_AND_PERSONAS.md`
 * (`App\Services\RoleplayScript`) and arrives as the page's `content` prop.
 */

export type DifficultyLevel = 1 | 2 | 3 | 4 | 5;

export type Outcome = 'transfer' | 'dq' | 'dnc';

export type Rebuttal = {
    title: string;
    lines: string[];
};

export type Objection = {
    id: string;
    meaning: string;
    seenPercent: number;
    lostPercent: number;
    weight: number;
    minLevel: DifficultyLevel;
    consumerLines: string[];
    /** The official rebuttal the doc quotes from the script. */
    rebuttal: Rebuttal;
};

export type Level = {
    level: DifficultyLevel;
    name: string;
    objectionRange: [number, number];
    temperament: string;
    outcomeMix: Record<Outcome, number>;
};

export type DqTrap = {
    id: string;
    hiddenTruth: string;
};

export type ScriptBranch = {
    answer: string;
    response: string;
};

export type ScriptSection = {
    id: string;
    title: string;
    lines: string[];
    branches: ScriptBranch[];
};

export type Disposition = {
    id: string;
    name: string;
    description: string;
};

export type DeliveryCriterion = {
    key: string;
    label: string;
    description: string;
};

export type RoleplayContent = {
    deliveryCriteria: DeliveryCriterion[];
    scriptSections: ScriptSection[];
    rebuttalCloser: string;
    rebuttals: Rebuttal[];
    complianceGuidelines: string[];
    dispositions: Disposition[];
    objections: Objection[];
    levels: Level[];
    dqTraps: DqTrap[];
    quirks: string[];
    quirkMinLevel: DifficultyLevel;
};

export type Lead = {
    name: string;
    state: string;
    zip: string;
};

/** What the browser knows about a live persona before the call ends. */
export type PublicPersona = {
    id: number;
    level: DifficultyLevel;
    lead: Lead;
    quirk: string | null;
    patience: number;
};

export type GradingCheck = {
    key: string;
    label: string;
    /** `null` when the check doesn't apply to this persona. */
    passed: boolean | null;
};

export type DeliveryScore = {
    key: string;
    label: string;
    /** `null` when it couldn't be measured or the AI review didn't finish. */
    score: number | null;
    source: 'measured' | 'existing' | 'ai';
    feedback: string;
};

/** `App\Services\RoleplayDeliveryGrader` output, scored from `guide.md`. */
export type Delivery = {
    criteria: DeliveryScore[];
    metrics: Record<string, unknown>;
    overall: number | null;
};

export type DeliveryStatus = 'pending' | 'done' | 'failed';

/** `App\Http\Resources\RoleplaySessionResource` once the call has ended. */
export type RoleplaySessionResult = {
    id: number;
    level: DifficultyLevel;
    lead: Lead;
    created_at: string;
    ended_at: string | null;
    disposition: string | null;
    passed: boolean | null;
    expected_outcome?: Outcome;
    /** The disposition id the call should have been coded as. */
    correct_disposition?: string;
    /** Why it differs from the persona's outcome, when it does. */
    correct_reason?: string | null;
    end_reason?: 'agent' | 'hung_up';
    checks?: GradingCheck[];
    delivery_status?: DeliveryStatus | null;
    delivery?: Delivery | null;
    dq_trap?: { id: string; hidden_truth: string } | null;
    objections?: Objection[];
    transcript?: string | null;
    recording_url?: string | null;
};

/** Matches `RoleplayScript::TRANSFER_ASK_SECTION`. */
export const TRANSFER_ASK_SECTION = 'ask_for_the_transfer';

/** The disposition a persona's correct outcome must be coded as. */
export const OUTCOME_DISPOSITION: Record<Outcome, string> = {
    transfer: 'transfer',
    dq: 'dq',
    dnc: 'dnc',
};

export const OUTCOME_LABELS: Record<Outcome, string> = {
    transfer: 'Transfer',
    dq: 'DQ',
    dnc: 'DNC',
};

/** Starting patience is `10 - level`. */
export function startingPatience(level: DifficultyLevel): number {
    return 10 - level;
}

/**
 * Replaces the script's placeholders with the lead's details and the
 * trainee's name.
 */
export function fillScriptPlaceholders(
    text: string,
    lead: Lead,
    agentName: string,
): string {
    const firstName = lead.name.split(' ')[0];

    return text
        .replace(/\(Customer'?s? Name\)/gi, firstName)
        .replace(/\(state\)/gi, lead.state)
        .replace(/\(zip code\)/gi, lead.zip)
        .replace(/_{3,}/g, agentName);
}
