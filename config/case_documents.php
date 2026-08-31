<?php

return [
    'uploads_enabled' => (bool) env('CASE_DOCUMENT_UPLOADS_ENABLED', true)
        && ! in_array(env('APP_ENV', 'production'), ['demo', 'sandbox'], true),
    'quarantine_disk' => env('CASE_DOCUMENT_QUARANTINE_DISK', 'case_quarantine'),
    'released_disk' => env('CASE_DOCUMENT_RELEASED_DISK', 'case_documents'),
    'clamd_socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
    'scan_timeout_seconds' => (int) env('CLAMAV_SCAN_TIMEOUT', 45),
    'max_signature_age_hours' => (int) env('CLAMAV_MAX_SIGNATURE_AGE_HOURS', 24),
    'max_bytes' => 20 * 1024 * 1024,
    'max_image_pixels' => 40_000_000,
    'max_attempts_per_minute' => 5,
    'max_organisation_bytes_per_hour' => 100 * 1024 * 1024,
    'max_non_terminal_scans' => 25,
];
