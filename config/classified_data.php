<?php

return [
    'encryption' => [
        'current_version' => env('CLASSIFIED_DATA_KEY_CURRENT'),
        'keys' => json_decode((string) env('CLASSIFIED_DATA_KEYS', '{}'), true) ?: [],
    ],

    'contact_index' => [
        'current_version' => env('CONTACT_INDEX_KEY_CURRENT'),
        'previous_version' => env('CONTACT_INDEX_KEY_PREVIOUS'),
        'keys' => json_decode((string) env('CONTACT_INDEX_KEYS', '{}'), true) ?: [],
    ],
];
