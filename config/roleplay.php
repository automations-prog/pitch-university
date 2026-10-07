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
        'pace_wpm_per_point' => 15,

        // Silence between the consumer finishing and the agent starting.
        'dead_air_ms' => 3000,

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

];
