<?php

return [
    'trial_days' => (int) env('RADAR_TRIAL_DAYS', 7),
    'digest_limit' => (int) env('RADAR_DIGEST_LIMIT', 8),
    'reminder_lead_minutes' => (int) env('RADAR_REMINDER_LEAD_MINUTES', 60),
    'max_preferences' => (int) env('RADAR_MAX_PREFERENCES', 12),
    'lots_url' => env('RADAR_LOTS_URL', 'https://acmachado14.github.io/leilao-radar/data/lotes.json'),
    'lots_path' => env('RADAR_LOTS_PATH'),
    'whatsapp' => [
        'enabled' => (bool) env('RADAR_WHATSAPP_ENABLED', false),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template' => env('WHATSAPP_TEMPLATE', 'radar_lote_alert'),
    ],
    'gemini' => [
        'api_key' => env('RADAR_GEMINI_API_KEY'),
        'model' => env('RADAR_GEMINI_MODEL', 'gemini-3.6-flash'),
        'max_images' => (int) env('RADAR_GEMINI_MAX_IMAGES', 4),
    ],
];
