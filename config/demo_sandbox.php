<?php

return [
    'enabled' => in_array(env('APP_ENV'), ['local', 'demo'], true)
        && env('DEMO_SANDBOX_ENABLED', true),
    'maximum_lifetime_hours' => 24,
];
