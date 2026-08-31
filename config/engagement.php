<?php

return [
    'simulation_only' => env('ENGAGEMENT_SIMULATION_ONLY', true),
    'frequency_cap_days' => (int) env('ENGAGEMENT_FREQUENCY_CAP_DAYS', 7),
];
