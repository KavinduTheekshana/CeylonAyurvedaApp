<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Home Visit Fee
    |--------------------------------------------------------------------------
    |
    | This is the standard fee charged for home visit services.
    | This fee is applied AFTER any coupon discounts.
    | The fee is NOT eligible for coupon discounts.
    |
    */
    'home_visit_fee' => env('HOME_VISIT_FEE', 19.99),

    /*
    |--------------------------------------------------------------------------
    | Visit Types
    |--------------------------------------------------------------------------
    |
    | Available visit types for bookings
    |
    */
    'visit_types' => [
        'home' => 'Home Visit',
        'branch' => 'Branch Visit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Visit Type Options
    |--------------------------------------------------------------------------
    |
    | Configuration for each visit type
    |
    */
    'visit_type_config' => [
        'home' => [
            'label' => 'Home Visit',
            'description' => 'We come to your location',
            'fee' => 19.99,
            'requires_address' => true,
            'icon' => 'home',
        ],
        'branch' => [
            'label' => 'Branch Visit',
            'description' => 'Visit our clinic',
            'fee' => 0,
            'requires_address' => false,
            'icon' => 'building',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking Status Options
    |--------------------------------------------------------------------------
    |
    | Available booking statuses
    |
    */
    'statuses' => [
        'pending' => 'Pending',
        'pending_payment' => 'Pending Payment',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | Available payment methods
    |
    */
    'payment_methods' => [
        'card' => 'Card Payment',
        'bank' => 'Bank Transfer',
        'cash' => 'Cash',
    ],
];