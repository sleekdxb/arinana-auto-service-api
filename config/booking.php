<?php

return [

    'operating_days' => array_map('intval', explode(',', env('BOOKING_OPERATING_DAYS', '1,2,3,4,5,6'))),

    'opens_at' => env('BOOKING_OPENS_AT', '08:00'),

    'closes_at' => env('BOOKING_CLOSES_AT', '16:00'),

    'slot_minutes' => (int) env('BOOKING_SLOT_MINUTES', 60),

    'slot_capacity' => (int) env('BOOKING_SLOT_CAPACITY', 3),

    'max_days' => (int) env('BOOKING_MAX_DAYS', 60),

    'inactive_states' => ['CANCELLED', 'CANCELED', 'REJECTED'],

    'timezone' => env('BOOKING_TIMEZONE', 'America/Toronto'),

];
