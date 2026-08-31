<?php

return [
    'disk' => env('AUDIT_RECOVERY_DISK', 'audit_recovery'),
    'signing_key' => env('AUDIT_DIGEST_SIGNING_KEY'),
    'alert_log_channel' => env('AUDIT_INTEGRITY_ALERT_LOG_CHANNEL', 'stack'),
];
