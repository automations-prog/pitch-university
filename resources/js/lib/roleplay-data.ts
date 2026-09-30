/**
 * Static roleplay content, transcribed verbatim from
 * `ai-roleplay/medicare_script.md` and `ai-roleplay/OBJECTIONS_AND_PERSONAS.md`.
 * Those two docs are the source of truth — change them first, then mirror
 * the change here word for word.
 */

export type DifficultyLevel = 1 | 2 | 3 | 4 | 5;

export type Outcome = 'transfer' | 'dq' | 'dnc';

export type RebuttalKey =
    | 'not_interested'
    | 'busy'
    | 'already_have_it'
    | 'dont_want_change'
    | 'just_did_best'
    | 'just_did_dont_qualify'
    | 'who_are_you'
    | 'scam'
    | 'what_benefits'
    | 'how_get_info'
    | 'dont_know'
    | 'dnc'
    | 'extra_not_interested';

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
    /** Official rebuttal, as a script heading key, or a verbatim script line. */
    rebuttal: RebuttalKey | { line: string };
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

export const REBUTTAL_CLOSER =
    "Every rebuttal ends the same way: GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE.";

export const REBUTTALS: Record<RebuttalKey, Rebuttal> = {
    not_interested: {
        title: "I'm not interested",
        lines: [
            "I understand, but MANY people are finding out RIGHT now that they ACTUALLY qualify for WAY more than they're receiving.",
        ],
    },
    busy: {
        title: "I'm busy / Call me back",
        lines: [
            "Oh, let me assure you this will be SUPER brief. I just have 3 SUPER quick questions and I'll bring the agent on.",
        ],
    },
    already_have_it: {
        title: "I've already got it",
        lines: [
            'Oh no, I understand. We were just calling about the increases. There have been some NEW allowances and NEW increases that have JUST become available.',
        ],
    },
    dont_want_change: {
        title: "I don't want to change / Do I have to change?",
        lines: [
            "Oh (Customer's Name), changing your insurance is ONLY something you can do. I don't even have the power to do that. There have just been some new allowances and new benefits that have JUST become available. We're just here to facilitate your free review.",
        ],
    },
    just_did_best: {
        title: 'I just did this and they said I have the best / everything',
        lines: [
            'Oh, you probably spoke with one of our small competitors. But I DO see here that you may be ENTITLED to some additional food benefits since the food prices HAVE gone up.',
        ],
    },
    just_did_dont_qualify: {
        title: "I just did this and they said I don't qualify",
        lines: [
            "Oh, you probably spoke with one of our small competitors. The difference is WE work with over 25 different health insurance providers, so people ARE finding that with US, they qualify for WAY more benefits than they're receiving.",
        ],
    },
    who_are_you: {
        title: 'Who are you with?',
        lines: [
            "Oh, I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review.",
        ],
    },
    scam: {
        title: "This is a scam / How do I know this isn't a scam",
        lines: [
            "Oh, let me assure you (customer's name), I'm not asking for any credit card or banking information. I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review.",
        ],
    },
    what_benefits: {
        title: 'What new benefits / What is that card for?',
        lines: [
            "So the healthy food card is there to help you with groceries on a monthly basis, and the Flex Card covers expenses such as dental, hearing, vision and utilities. There's also a Part B give back for Medicare, which helps cover some, if not ALL, of your Part B premium.",
        ],
    },
    how_get_info: {
        title: 'How did you get my information?',
        lines: [
            "Oh, so either you or a family member went online and expressed interest in a free review. We're just here to facilitate that for you.",
        ],
    },
    dont_know: {
        title: "Any questions you don't know the answer to / How much do I qualify for, etc?",
        lines: [
            'That would be a great question for the agent once I get you there.',
        ],
    },
    dnc: {
        title: 'DNC Disclaimer',
        lines: [
            "No problem, I'll add you to our internal do not call list immediately and you won't receive any further calls from our company. We do apologize for any inconvenience, have a great day. (END CALL AND CODE AS DNC)",
        ],
    },
    extra_not_interested: {
        title: 'EXTRA NOT INTERESTED REBUTTALS',
        lines: [
            "Well, I understand, but you've worked your whole life for these Medicare benefits. Give the agent JUST a few minutes to review and compare your plan to make SURE you're not missing out on ANYTHING you may be entitled to.",
            'I understand, but these new benefits JUST became available, so your current plan may NOT include them. Now, we DO have on file that you may qualify for additional benefits or a new allowance.',
        ],
    },
};

export const OBJECTIONS: Objection[] = [
    {
        id: 'already_have_it',
        meaning: "I've already got it",
        seenPercent: 16.3,
        lostPercent: 3.5,
        weight: 168,
        minLevel: 1,
        consumerLines: [
            'I already have that card.',
            'I already get all that stuff.',
        ],
        rebuttal: 'already_have_it',
    },
    {
        id: 'busy',
        meaning: "I'm busy / call me back",
        seenPercent: 8.3,
        lostPercent: 14.9,
        weight: 105,
        minLevel: 1,
        consumerLines: [
            "I'm busy right now, call me back later.",
            "I'm at the doctor, I can't talk.",
            "I'm literally driving right now, man.",
        ],
        rebuttal: 'busy',
    },
    {
        id: 'dont_want_change',
        meaning: "I don't want to change / I like my plan",
        seenPercent: 8.2,
        lostPercent: 4.9,
        weight: 89,
        minLevel: 2,
        consumerLines: [
            "I like my plan, I'm not changing anything.",
            'Do I have to change my insurance?',
            "I've had the same plan for ten years and I'm happy with it.",
        ],
        rebuttal: 'dont_want_change',
    },
    {
        id: 'what_benefits',
        meaning: "What benefits? What's the card for?",
        seenPercent: 7.8,
        lostPercent: 0.6,
        weight: 79,
        minLevel: 1,
        consumerLines: [
            "What card? What's it for?",
            'What kind of benefits are we talking about?',
        ],
        rebuttal: 'what_benefits',
    },
    {
        id: 'who_are_you',
        meaning: 'Who are you with? / Why are you calling me?',
        seenPercent: 7.1,
        lostPercent: 1.0,
        weight: 72,
        minLevel: 1,
        consumerLines: [
            'Who is this? Who are you with?',
            'Why are you calling me?',
            'What company did you say?',
            'Why the f*** are you calling me?',
            'Who the hell is this and why do you keep calling?',
        ],
        rebuttal: 'who_are_you',
    },
    {
        id: 'never_received_card',
        meaning: 'I never got any card',
        seenPercent: 6.9,
        lostPercent: 0.0,
        weight: 69,
        minLevel: 1,
        consumerLines: [
            'I never got no card.',
            'What card? I never received anything from Medicare.',
            "Y'all keep saying card, I ain't never got a damn card.",
        ],
        rebuttal: 'already_have_it',
    },
    {
        id: 'doubts_eligibility',
        meaning: "I don't think I qualify for that",
        seenPercent: 6.6,
        lostPercent: 4.2,
        weight: 72,
        minLevel: 1,
        consumerLines: [
            "I don't think I qualify for anything like that.",
            'I make too much, they never give me nothing.',
        ],
        rebuttal: 'not_interested',
    },
    {
        id: 'has_different_card',
        meaning: "I don't have that card / I have a different card",
        seenPercent: 6.4,
        lostPercent: 0.4,
        weight: 65,
        minLevel: 1,
        consumerLines: [
            "I don't have a red, white and blue card. I have my Humana card.",
            "The card I got is blue and white, it's from my plan.",
        ],
        rebuttal: {
            line: 'Okay, no problem, but you DO have your Medicare number, correct?',
        },
    },
    {
        id: 'just_did_dont_qualify',
        meaning: "I just did this — they said I don't qualify",
        seenPercent: 4.9,
        lostPercent: 3.3,
        weight: 54,
        minLevel: 3,
        consumerLines: [
            "I already did this and they told me I don't qualify for nothing.",
        ],
        rebuttal: 'just_did_dont_qualify',
    },
    {
        id: 'transfer_no',
        meaning: "Refuses the transfer / won't wait for the specialist",
        seenPercent: 4.3,
        lostPercent: 28.3,
        weight: 85,
        minLevel: 2,
        consumerLines: [
            "No, I don't have a few minutes.",
            "I'm not waiting on hold for somebody.",
        ],
        rebuttal: 'extra_not_interested',
    },
    {
        id: 'not_interested',
        meaning: "I'm not interested",
        seenPercent: 3.7,
        lostPercent: 24.0,
        weight: 73,
        minLevel: 1,
        consumerLines: [
            "I'm not interested.",
            'Nah, not interested, thanks.',
            "I said I'm not interested, what part of that don't you get?",
        ],
        rebuttal: 'not_interested',
    },
    {
        id: 'just_did_best',
        meaning: 'I just did this — they said I have the best',
        seenPercent: 3.0,
        lostPercent: 8.3,
        weight: 42,
        minLevel: 2,
        consumerLines: [
            'Somebody just called me last week and said I already have the best plan.',
        ],
        rebuttal: 'just_did_best',
    },
    {
        id: 'too_many_calls',
        meaning: 'This is the 16th call today',
        seenPercent: 2.6,
        lostPercent: 6.7,
        weight: 36,
        minLevel: 3,
        consumerLines: [
            "This is the 16th telemarketing call I've had today and I am so sick of it.",
            "Y'all been calling me all damn day, I'm tired of it.",
        ],
        rebuttal: 'busy',
    },
    {
        id: 'personal_info',
        meaning: "I'm not giving out personal information",
        seenPercent: 2.5,
        lostPercent: 5.6,
        weight: 33,
        minLevel: 3,
        consumerLines: [
            "I don't give my information out over the phone.",
            "I'm not giving you my Medicare number.",
            'Do I have to give personal information?',
        ],
        rebuttal: 'scam',
    },
    {
        id: 'never_get_anything',
        meaning: "Y'all call all the time and I never get anything",
        seenPercent: 2.1,
        lostPercent: 2.3,
        weight: 24,
        minLevel: 2,
        consumerLines: [
            "They call me all the time about this and I don't never receive nothing.",
            "Every week somebody calls about a card and I ain't never got one.",
        ],
        rebuttal: 'already_have_it',
    },
    {
        id: 'scam',
        meaning: 'This is a scam',
        seenPercent: 1.6,
        lostPercent: 7.7,
        weight: 28,
        minLevel: 2,
        consumerLines: [
            'This sounds like a scam.',
            "How do I know you're not a scammer?",
            'You people are all scammers, I see it on the news every day.',
        ],
        rebuttal: 'scam',
    },
    {
        id: 'how_much',
        meaning: "How much do I qualify for? (can't answer)",
        seenPercent: 1.5,
        lostPercent: 3.1,
        weight: 20,
        minLevel: 2,
        consumerLines: [
            'How much money is on the card?',
            'How much would I get exactly?',
        ],
        rebuttal: 'dont_know',
    },
    {
        id: 'is_this_insurance',
        meaning: 'Is this insurance? Part C?',
        seenPercent: 1.4,
        lostPercent: 0.0,
        weight: 14,
        minLevel: 3,
        consumerLines: [
            'Is this insurance? The Part C insurance?',
            'Is this one of those Medicare Advantage things they keep pushing?',
        ],
        rebuttal: 'dont_know',
    },
    {
        id: 'afraid_to_lose_coverage',
        meaning: "I'm not getting scammed out of my coverage",
        seenPercent: 1.0,
        lostPercent: 0.0,
        weight: 10,
        minLevel: 3,
        consumerLines: [
            "I'm not going to do anything that gets me scammed out of the coverage I already have.",
            "If I have to change my insurance plan to get whatever you're selling, I don't want it.",
        ],
        rebuttal: 'dont_want_change',
    },
    {
        id: 'trying_to_sell_me',
        meaning: "You're just trying to sell me something",
        seenPercent: 0.9,
        lostPercent: 11.1,
        weight: 26,
        minLevel: 3,
        consumerLines: [
            "You're just trying to sell me something.",
            "What are you selling? Just tell me what you're selling.",
        ],
        rebuttal: 'dont_want_change',
    },
    {
        id: 'how_get_info',
        meaning: 'How did you get my number?',
        seenPercent: 0.7,
        lostPercent: 6.7,
        weight: 17,
        minLevel: 2,
        consumerLines: [
            'How did you get my number?',
            "Where'd you get my information?",
            'Who gave you my phone number? I never signed up for anything.',
            'How the f*** did you get my number?',
            'Where the hell did you get my number, huh?',
        ],
        rebuttal: 'how_get_info',
    },
    {
        id: 'will_i_get_more',
        meaning: "So they're going to give me more?",
        seenPercent: 0.6,
        lostPercent: 0.0,
        weight: 6,
        minLevel: 2,
        consumerLines: [
            'Are they going to give me more?',
            'So how much more money am I getting on the card?',
        ],
        rebuttal: 'dont_know',
    },
    {
        id: 'already_enrolled',
        meaning: 'I already did my insurance for next year',
        seenPercent: 0.6,
        lostPercent: 16.7,
        weight: 31,
        minLevel: 2,
        consumerLines: [
            'I already did my insurance for next year. I got Humana.',
            "My plan's all set for next year, I just did all that.",
        ],
        rebuttal: 'already_have_it',
    },
    {
        id: 'caller_id_scam',
        meaning: "My phone said 'potential scam'",
        seenPercent: 0.4,
        lostPercent: 6.7,
        weight: 14,
        minLevel: 2,
        consumerLines: [
            'Is this a scam? Because it came up as potential scam on my phone.',
            "I've seen this number come up all week and it says potential scam, so I kept declining it.",
            'My phone says Scam Likely. Why the hell should I trust you?',
        ],
        rebuttal: 'scam',
    },
];

export const LEVELS: Level[] = [
    {
        level: 1,
        name: 'Warm Up',
        objectionRange: [0, 1],
        temperament:
            'polite, a little distracted, cooperative once they understand',
        outcomeMix: { transfer: 100, dq: 0, dnc: 0 },
    },
    {
        level: 2,
        name: 'Guarded',
        objectionRange: [1, 2],
        temperament: 'guarded, impatient, wants to get off the phone',
        outcomeMix: { transfer: 100, dq: 0, dnc: 0 },
    },
    {
        level: 3,
        name: 'Skeptical',
        objectionRange: [2, 3],
        temperament:
            "skeptical, sharp, asks pointed questions, won't give info easily",
        outcomeMix: { transfer: 82, dq: 18, dnc: 0 },
    },
    {
        level: 4,
        name: 'Hostile',
        objectionRange: [3, 4],
        temperament:
            'rude and hostile, interrupts, curses at the agent, sarcastic',
        outcomeMix: { transfer: 82, dq: 18, dnc: 0 },
    },
    {
        level: 5,
        name: 'Nightmare',
        objectionRange: [3, 5],
        temperament:
            'unhinged: yells, swears constantly, rambles off-topic, accuses the agent, may be confused or hard of hearing, may try to trap the agent',
        outcomeMix: { transfer: 61, dq: 14, dnc: 25 },
    },
];

/** Starting patience is `10 - level`. */
export function startingPatience(level: DifficultyLevel): number {
    return 10 - level;
}

/** A quirk is attached to every persona at level 2 and up. */
export const QUIRK_MIN_LEVEL: DifficultyLevel = 2;

export const DQ_TRAPS: DqTrap[] = [
    { id: 'va', hiddenTruth: 'Has VA health care (military veteran).' },
    {
        id: 'tricare',
        hiddenTruth:
            "Has Tricare for Life through a spouse's military service.",
    },
    {
        id: 'employer',
        hiddenTruth: 'Still has insurance through their employer.',
    },
    {
        id: 'retiree',
        hiddenTruth:
            'Has retiree health insurance from a former job (city pension plan).',
    },
    {
        id: 'no_part_b',
        hiddenTruth: 'Only has Part A, never signed up for Part B.',
    },
    {
        id: 'medicaid_only',
        hiddenTruth: 'Only has Medicaid. No Medicare Parts A & B at all.',
    },
];

export const QUIRKS: string[] = [
    'TV blaring in the background',
    'hard of hearing, asks you to repeat things',
    "keeps mentioning their grandson who 'handles this stuff'",
    'eating while talking',
    'was just woken up from a nap',
    'talks about their bad knee',
    'dog barking the whole call',
    'just got off the phone with another telemarketer',
    'on a cheap phone that cuts out',
    "calls the agent 'baby' or 'hon'",
    'just woke up and is groggy',
    "hard of hearing and keeps asking 'who are you?'",
    'wants to tell you about their health problems',
    'has had 15 sales calls today',
    'at work and distracted',
    "very religious, says 'amen' a lot",
    'thinks every caller is trying to switch their insurance',
];

export const SCRIPT_SECTIONS: ScriptSection[] = [
    {
        id: 'opening',
        title: 'Opening',
        lines: [
            'Hey, good morning/afternoon (Customer Name)?',
            "Hey (Customer Name), this is ___, I'm with America's Health on a recorded line. We were just giving you a quick call today about your food and utility card that you never received. That card, it IS exclusively for members who have Medicare Parts A and B. You DO still have your Medicare Parts A and B, correct? (Let them confirm)",
        ],
        branches: [
            { answer: 'Yes', response: '(continue)' },
            {
                answer: 'No',
                response:
                    "'There must have been a mistake. Thank you for your time, have a great day.' (Code as DQ)",
            },
            {
                answer: "I don't know",
                response:
                    "'I understand. Well, do you have the red, white and blue card by chance?' (If yes, follow up with) 'Okay, perfect, so then you DO have Medicare Parts A and B, correct?' (If yes, skip to 'Do you receive Medicaid')",
            },
        ],
    },
    {
        id: 'double_confirm',
        title: 'Double-confirm the card',
        lines: [
            'Okay, perfect, and JUST to double confirm, that IS the red, white and blue card, correct? (Let them confirm)',
        ],
        branches: [
            { answer: 'Yes', response: '(continue)' },
            {
                answer: "I don't have that, I have ____ card",
                response:
                    "'Okay, no problem, but you DO have your Medicare number, correct?' (If yes, continue. If no, read the No line below.)",
            },
            {
                answer: "No / I'm not giving my number out / I lost it",
                response:
                    "'Okay, you WOULD have to know your Medicare number or Social Security number to review those increases. You don't need to give it to me, but the licensed agent may need it. Do you know either of those?' (Either way, continue)",
            },
        ],
    },
    {
        id: 'medicaid',
        title: 'Medicaid',
        lines: [
            'Perfect, and do you receive Medicaid along with your Medicare plan?',
        ],
        branches: [{ answer: 'Yes/No', response: '(continue)' }],
    },
    {
        id: 'state_zip',
        title: 'State and ZIP',
        lines: [
            "Perfect, thanks so much for confirming that for me. So yeah, it DOES look like you may be ENTITLED to some additional food benefits since the food prices have gone up. If that's sent, I DO still have you out in (state), with the zip code (zip code), correct?",
        ],
        branches: [],
    },
    {
        id: 'work_va',
        title: 'Work / VA insurance',
        lines: [
            'Okay, LAST question here, do you have insurance through your work or the VA?',
        ],
        branches: [
            {
                answer: 'Yes',
                response:
                    "'Oh, there must have been a mistake. Thank you for your time, have a great day.' (CODE AS DQ)",
            },
            { answer: 'No', response: '(continue)' },
        ],
    },
    {
        id: 'transfer_ask',
        title: 'Ask for the transfer',
        lines: [
            'Perfect, well that is EVERYTHING I need. So, a Medicare specialist is coming on the line now to review the additional food benefits for you. Give them JUST a few minutes to go over them with you, okay? - AFFIRM (STOP AND WAIT. The consumer MUST say yes before you click transfer)',
        ],
        branches: [
            {
                answer: 'Yes',
                response: '(Now click transfer and continue)',
            },
            {
                answer: 'No',
                response:
                    "(Do NOT click transfer. Read an EXTRA NOT INTERESTED rebuttal, then go back to the ask) 'Give them JUST a few minutes to go over them with you, okay?'",
            },
        ],
    },
    {
        id: 'cold_transfer',
        title: 'Cold transfer',
        lines: [
            "Alright, so you'll hear a little bit of ringing, that's just me connecting them in, and once they pick up they'll ask you for your name, okay? (You already have their permission to transfer. You do NOT need to ask for another okay here or once the licensed agent connects.)",
            "In the meantime, how's that weather out there today? (FLUFF AWAY)",
        ],
        branches: [],
    },
];

export const COMPLIANCE_GUIDELINES: string[] = [
    "Make sure you say you're on a recorded line with America's Health.",
    'Make sure you double confirm Medicare Parts A and B.',
    'Make sure you confirm ZIP code and state.',
    "Make sure you verify they don't have insurance through an employer or the VA (retirement/military/etc.).",
    'Make sure you tell them they are being transferred to a specialist.',
    'Wait for verbal confirmation to transfer. You MUST ask for the okay and you must wait for permission. This is the ONLY time we are asking for permission in the call!',
    "Stop talking once the agent connects; they will usually ask who they're speaking with. Once the lead responds, complete the transfer process. Remember, we are doing 'cold transfers' only!",
    'May and maybe are your best friends! Make sure you ALWAYS say may or maybe when mentioning something the lead may qualify for!',
    'Stick to the script. Going off script and freestyling is where compliance is broken the most. Stay scripted.',
];

export const DISPOSITIONS: Disposition[] = [
    {
        id: 'answering_machine',
        name: 'Answering Machine',
        description:
            "voicemail or ringing with no answer; someone else answers and says they're not there.",
    },
    {
        id: 'dead_air',
        name: 'Dead Air',
        description:
            'you call their name twice at the beginning of the call but get no response.',
    },
    {
        id: 'not_interested',
        name: 'Not Interested (NI)',
        description:
            'they acknowledge you when you greet them but hang up at any point after.',
    },
    {
        id: 'hung_up_transfer',
        name: 'Hung-Up Transfer',
        description:
            'they hang up during or after starting the transfer process.',
    },
    {
        id: 'robo',
        name: 'Robo',
        description: 'fake automated voice, not a real person.',
    },
    {
        id: 'transfer',
        name: 'Transfer',
        description:
            'lead and agent connect & speak (cold transfer completed).',
    },
    {
        id: 'wrong_number',
        name: 'Wrong Number',
        description: 'person says you have the wrong number.',
    },
    {
        id: 'no_english',
        name: 'No English',
        description: 'caller cannot speak English.',
    },
    {
        id: 'dnc',
        name: 'DNC (Do Not Call)',
        description:
            "It's DNC only when they tell you to stop calling / never call again / take them off the list, or threaten you. Swearing by itself is an objection.",
    },
    {
        id: 'dq',
        name: 'DQ (Disqualified)',
        description:
            'no Medicare Parts A & B; military coverage (VA, Tricare, Tricare for Life, CHAMPVA); insurance through work/retirement; covered by a Native American tribe.',
    },
    {
        id: 'conference_ended',
        name: 'Conference Ended',
        description:
            "every transfer attempt results in the agent's line disconnecting, a busy signal, or number-not-in-service.",
    },
    {
        id: 'no_agent',
        name: 'No Agent',
        description:
            "5 transfer attempts, waiting at least 20 seconds on each, and still can't reach an agent.",
    },
];

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

export function rebuttalFor(objection: Objection): Rebuttal {
    if (typeof objection.rebuttal === 'string') {
        return REBUTTALS[objection.rebuttal];
    }

    return {
        title: 'Script line',
        lines: [objection.rebuttal.line],
    };
}
