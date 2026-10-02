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
        // Words per minute while the agent is speaking. Seniors need it clear.
        'pace_min_wpm' => 130,
        'pace_max_wpm' => 160,

        // Silence between the consumer finishing and the agent starting.
        'dead_air_ms' => 3000,

        // Filler words ("um", "uh", ...) per 100 agent words that still earn a 3.
        'fillers_per_100_ok' => 3,

        // Seconds to resolve an objection before it counts as slow.
        'objection_slow_seconds' => 20,

        // Model for the AI-judged criteria (structured outputs).
        'grader_model' => env('OPENAI_GRADER_MODEL', 'gpt-5-mini'),
    ],

];
