<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery scoring thresholds
    |--------------------------------------------------------------------------
    |
    | Used to turn the measured delivery metrics (see `guide.md` and
    | RoleplayDeliveryMetrics) into 1–5 scores. Tune these after comparing
    | scores with a trainer's judgment on real calls.
    |
    */

    'delivery' => [
        // Words per minute while the agent is speaking. Seniors need it clear,
        // but confident top reps run fast, so the top end is generous. Each
        // this many wpm outside the range costs one point.
        'pace_min_wpm' => 130,
        'pace_max_wpm' => 185,
        'pace_wpm_per_point' => 20,

        // Silence between the consumer finishing and the agent starting.
        'dead_air_ms' => 4000,

        // Filler words ("um", "uh", ...) per 100 agent words that still earn a 3.
        'fillers_per_100_ok' => 3,

        // Seconds to resolve an objection before it counts as slow.
        'objection_slow_seconds' => 20,

        // Model for the AI-judged criteria (structured outputs).
        'grader_model' => env('OPENAI_GRADER_MODEL', 'gpt-5-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cold transfer
    |--------------------------------------------------------------------------
    |
    | The transfer rings for 30 seconds (`TRANSFER_FLUFF_MS` in
    | use-roleplay-realtime-call.ts) while the trainee fluffs with the
    | consumer. This is how much of that they must spend talking to pass
    | the fluff check.
    |
    */

    'transfer' => [
        'fluff_min_ms' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meals brush-offs
    |--------------------------------------------------------------------------
    |
    | On Medicaid meals calls a brush-off ("not interested", "I don't need
    | it", "call me back") is lost if the agent pauses: the median call with
    | no rebuttal lasts 27 seconds. The longest silence after one that still
    | counts as coming straight back.
    |
    */

    'meals' => [
        'brush_off_pause_max_ms' => 3000,
    ],

    /*
    |--------------------------------------------------------------------------
    | ACA objection focus
    |--------------------------------------------------------------------------
    |
    | Objections trainers want drilled: drawn more often than their real-call
    | weight, and every call from `focus_guaranteed_from_level` up has at
    | least one. Brush-offs are what lose calls (`ACA_objection_playbook.md`),
    | so the hardest levels draw them more often too.
    |
    */

    'aca' => [
        'focus_objections' => ['dont_want_change', 'is_it_money', 'how_got_info'],
        'focus_weight_multiplier' => 3,
        'focus_guaranteed_from_level' => 4,

        'brush_off_objections' => ['not_interested', 'busy_callback', 'hesitation'],
        'brush_off_weight_multiplier' => 2,
        'brush_off_from_level' => 4,

        // Disqualifiers that hide behind vague wording, the only ones drawn
        // from this level.
        'tricky_dq_traps' => ['spousal_work', 'private', 'state_insurance', 'ssdi'],
        'tricky_dq_traps_from_level' => 5,

        // From this level, consumers with a plan name one of the carriers
        // we see most, and the "insurance not familiar" objection is drawn
        // more often, so the agent has to ask "through the Marketplace?".
        'named_carrier_from_level' => 3,
        'common_carriers' => ['Oscar', 'Blue Cross', 'Ambetter'],
        'named_carrier_weight_multiplier' => 2,
    ],

];
