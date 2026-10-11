<?php

/*
 * StagePilot business settings (same values as the Python prototype).
 */
return [

    // Public demo account ("Essayer la démo" button): fictional data, never connected to Gmail, reset at 03:00.
    'demo_enabled' => (bool) env('DEMO_ENABLED', true),

    // Who may create an account (comma-separated emails in .env). Empty = anyone (fine on your own PC, not online).
    'invited_emails' => array_values(array_filter(array_map(
        fn ($email) => mb_strtolower(trim($email)),
        explode(',', (string) env('INVITED_EMAILS', '')),
    ))),

    // Anti-spam: never more than this per student and per day, whatever the profile says.
    'max_daily_quota' => 20,

    // Random pause between two emails, in seconds.
    'pause_between_emails' => [60, 180],

    // One follow-up, this many days after the application, if no reply.
    'follow_up_after_days' => 7,

    // Hooks written per student by the 08:00 run (each one costs one AI call).
    'prepare_batch_size' => 10,

    // Never more hooks than this per student and per day (08:00 run + "Préparer" button together).
    'prepare_daily_limit' => 20,

    // A preparation stops after trying this many companies, even if it has not reached the number asked.
    'prepare_max_companies' => 60,

    // No sending on these days (French public holidays) nor on weekends.
    'holidays' => [
        '2026-11-01', '2026-11-11', '2026-12-25', '2027-01-01', '2027-04-05', '2027-05-01', '2027-05-08',
        '2027-05-13', '2027-05-24', '2027-07-14', '2027-08-15', '2027-11-01', '2027-11-11', '2027-12-25',
    ],

    /*
     * Who writes the hooks. Every hook is checked the same way afterwards (rules + proof on the site).
     *  - template : free, no AI, sentences built from the site and the student's profile (default)
     *  - ollama   : free, an AI running on your own computer (https://ollama.com)
     *  - groq     : free plan of GroqCloud (no bank card), large open model gpt-oss-120b
     *  - mistral  : Mistral AI, paid (its free plan no longer gives API keys, October 2026)
     *  - claude   : paid, Anthropic API, best quality
     */
    'hook_provider' => env('HOOK_PROVIDER', 'template'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3:8b'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

    // Shown on the legal page (mentions légales, personal data requests)
    'contact_email' => env('CONTACT_EMAIL', 'aliakir679@gmail.com'),

    // Daily database backup (stagepilot:backup, 02:30): compressed files, the oldest are deleted
    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'dump_binary' => env('BACKUP_DUMP_BINARY', 'mysqldump'),
    ],
];
