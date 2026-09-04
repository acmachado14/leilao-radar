<?php

return [
    'trial_days' => (int) env('RADAR_TRIAL_DAYS', 7),
    'digest_limit' => (int) env('RADAR_DIGEST_LIMIT', 8),
    'lots_url' => env('RADAR_LOTS_URL', 'https://acmachado14.github.io/leilao-radar/data/lotes.json'),
    'lots_path' => env('RADAR_LOTS_PATH'),
    'whatsapp' => [
        'enabled' => (bool) env('RADAR_WHATSAPP_ENABLED', false),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template' => env('WHATSAPP_TEMPLATE', 'radar_lote_alert'),
    ],
];
