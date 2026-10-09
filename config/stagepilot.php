<?php

/*
 * StagePilot business settings (same values as the Python prototype).
 */
return [

    // Anti-spam: never more than this per student and per day, whatever the profile says.
    'max_daily_quota' => 20,

    // Random pause between two emails, in seconds.
    'pause_between_emails' => [60, 180],

    // One follow-up, this many days after the application, if no reply.
    'follow_up_after_days' => 7,

    // Hooks prepared per student and per run (each one costs one Claude call).
    'prepare_batch_size' => 10,

    // No sending on these days (French public holidays) nor on weekends.
    'holidays' => [
        '2026-11-01', '2026-11-11', '2026-12-25', '2027-01-01', '2027-04-05', '2027-05-01', '2027-05-08',
        '2027-05-13', '2027-05-24', '2027-07-14', '2027-08-15', '2027-11-01', '2027-11-11', '2027-12-25',
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],
];
