import { useCallback, useMemo, useState } from 'react';
import {
    SCRIPT_SECTIONS,
    rebuttalFor,
    type Objection,
    type Rebuttal,
} from '@/lib/roleplay-data';
import {
    consumerLineFor,
    type Lead,
    type Persona,
} from '@/lib/roleplay-persona';

export type TranscriptSpeaker = 'agent' | 'consumer' | 'system';

export type TranscriptLine = {
    id: number;
    speaker: TranscriptSpeaker;
    text: string;
};

export type EndReason = 'agent' | 'hung_up';

/** DQ traps that surface at the opening (no Parts A & B) vs. Work / VA. */
const OPENING_DQ_TRAPS = new Set(['no_part_b', 'medicaid_only']);

const TRANSFER_ASK_STEP = SCRIPT_SECTIONS.findIndex(
    (section) => section.id === 'transfer_ask',
);
const WORK_VA_STEP = SCRIPT_SECTIONS.findIndex(
    (section) => section.id === 'work_va',
);

/**
 * Replaces the script's placeholders with the mock lead's details and the
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

/**
 * Assigns each drawn objection to the script step it's raised after.
 * `transfer_no` is always raised at the transfer ask; the rest land on a
 * random step before it.
 */
function scheduleObjections(objections: Objection[]): Map<number, Objection[]> {
    const schedule = new Map<number, Objection[]>();

    objections.forEach((objection) => {
        const step =
            objection.id === 'transfer_no'
                ? TRANSFER_ASK_STEP
                : Math.floor(Math.random() * TRANSFER_ASK_STEP);

        schedule.set(step, [...(schedule.get(step) ?? []), objection]);
    });

    return schedule;
}

/**
 * Mock call engine for the UI-only roleplay: no AI yet. The trainee reads
 * each script step, the "consumer" answers with the persona's scheduled
 * objections, and unanswered objections cost patience.
 */
export function useRoleplayCall(persona: Persona, agentName: string) {
    const schedule = useMemo(
        () => scheduleObjections(persona.objections),
        [persona],
    );
    const dncStep = useMemo(
        () => Math.floor(Math.random() * TRANSFER_ASK_STEP),
        [persona],
    );

    const [transcript, setTranscript] = useState<TranscriptLine[]>(() => [
        {
            id: 0,
            speaker: 'system',
            text: `Dialing ${persona.lead.name}… the consumer picked up.`,
        },
    ]);
    const [currentStep, setCurrentStep] = useState(0);
    const [patience, setPatience] = useState(persona.patience);
    const [openObjections, setOpenObjections] = useState<Objection[]>([]);
    const [facedObjections, setFacedObjections] = useState<Objection[]>([]);
    const [endReason, setEndReason] = useState<EndReason | null>(null);

    const append = useCallback(
        (lines: Omit<TranscriptLine, 'id'>[]) =>
            setTranscript((current) => [
                ...current,
                ...lines.map((line, offset) => ({
                    ...line,
                    id: current.length + offset,
                })),
            ]),
        [],
    );

    const readStep = useCallback(() => {
        if (endReason || currentStep >= SCRIPT_SECTIONS.length) {
            return;
        }

        const section = SCRIPT_SECTIONS[currentStep];
        const lines: Omit<TranscriptLine, 'id'>[] = section.lines.map(
            (line) => ({
                speaker: 'agent',
                text: fillScriptPlaceholders(line, persona.lead, agentName),
            }),
        );

        let nextPatience = patience - openObjections.length;

        if (openObjections.length > 0) {
            lines.unshift({
                speaker: 'system',
                text: `You moved on without rebutting ${openObjections.length === 1 ? 'an objection' : `${openObjections.length} objections`}. Patience −${openObjections.length}.`,
            });
        }

        const raised = schedule.get(currentStep) ?? [];
        raised.forEach((objection) =>
            lines.push({
                speaker: 'consumer',
                text: consumerLineFor(objection, persona.level.level),
            }),
        );

        const trap = persona.dqTrap;
        const revealsTrap =
            trap !== null &&
            (OPENING_DQ_TRAPS.has(trap.id)
                ? currentStep === 0
                : currentStep === WORK_VA_STEP);

        if (revealsTrap) {
            lines.push({
                speaker: 'system',
                text: `The consumer answers truthfully: ${trap.hiddenTruth}`,
            });
        }

        if (persona.outcome === 'dnc' && currentStep === dncStep) {
            lines.push({
                speaker: 'consumer',
                text: 'Take me off your list and never call me again.',
            });
        }

        if (nextPatience <= 0) {
            nextPatience = 0;
            lines.push({
                speaker: 'system',
                text: 'The consumer ran out of patience and hung up.',
            });
            setEndReason('hung_up');
        }

        append(lines);
        setPatience(nextPatience);
        setOpenObjections(raised);
        setFacedObjections((current) => [...current, ...raised]);
        setCurrentStep((step) => step + 1);
    }, [
        agentName,
        append,
        currentStep,
        dncStep,
        endReason,
        openObjections,
        patience,
        persona,
        schedule,
    ]);

    /**
     * Reads one line of a rebuttal. It clears the first open objection that
     * rebuttal answers; reading the wrong rebuttal leaves it open.
     */
    const readRebuttal = useCallback(
        (rebuttal: Rebuttal, lineIndex: number) => {
            if (endReason) {
                return;
            }

            append([
                {
                    speaker: 'agent',
                    text: fillScriptPlaceholders(
                        rebuttal.lines[lineIndex],
                        persona.lead,
                        agentName,
                    ),
                },
            ]);

            setOpenObjections((current) => {
                const answered = current.find(
                    (objection) =>
                        rebuttalFor(objection).title === rebuttal.title,
                );

                return current.filter((objection) => objection !== answered);
            });
        },
        [agentName, append, endReason, persona.lead],
    );

    const endCall = useCallback(() => {
        setEndReason((current) => current ?? 'agent');
    }, []);

    return {
        transcript,
        currentStep,
        patience,
        openObjections,
        facedObjections,
        endReason,
        readStep,
        readRebuttal,
        endCall,
    };
}
