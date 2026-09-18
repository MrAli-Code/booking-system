<?php
return [
    'booking' => [
        'enabled' => true,
        'name' => 'Booking Engine',
        'description' => 'Core appointment booking system',
        'required' => true,
    ],
    'payment' => [
        'enabled' => true,
        'name' => 'Payment System',
        'description' => 'Multi-gateway payment processing',
        'required' => false,
    ],
    'crm' => [
        'enabled' => true,
        'name' => 'CRM System',
        'description' => 'Customer relationship management',
        'required' => false,
    ],
    'wallet' => [
        'enabled' => false,
        'name' => 'Wallet System',
        'description' => 'Internal credit wallet',
        'required' => false,
    ],
    'referral' => [
        'enabled' => false,
        'name' => 'Referral System',
        'description' => 'Customer referral with rewards',
        'required' => false,
    ],
    'notification' => [
        'enabled' => true,
        'name' => 'Notification System',
        'description' => 'Omnichannel notifications',
        'required' => false,
    ],
    'report' => [
        'enabled' => true,
        'name' => 'Report Engine',
        'description' => 'Analytics and reporting',
        'required' => false,
    ],
    'multi_currency' => [
        'enabled' => false,
        'name' => 'Multi-Currency',
        'description' => 'Support for IRR, USD, EUR, Crypto',
        'required' => false,
    ],
    'crypto' => [
        'enabled' => false,
        'name' => 'Crypto Payments',
        'description' => 'BTC, USDT, ETH payment support',
        'required' => false,
    ],
    'telegram' => [
        'enabled' => false,
        'name' => 'Telegram Bot',
        'description' => 'Telegram notification integration',
        'required' => false,
    ],
    'whatsapp' => [
        'enabled' => false,
        'name' => 'WhatsApp API',
        'description' => 'WhatsApp notification integration',
        'required' => false,
    ],
    'bale' => [
        'enabled' => false,
        'name' => 'Bale Messenger',
        'description' => 'Bale notification integration',
        'required' => false,
    ],
    'rubika' => [
        'enabled' => false,
        'name' => 'Rubika Messenger',
        'description' => 'Rubika notification integration',
        'required' => false,
    ],
    'medical_mode' => [
        'enabled' => false,
        'name' => 'Medical Mode',
        'description' => 'Image upload, needs-review workflow',
        'required' => false,
    ],
    'waitlist' => [
        'enabled' => false,
        'name' => 'Waitlist FOMO',
        'description' => 'Instant notification on cancellations',
        'required' => false,
    ],
    'rating' => [
        'enabled' => true,
        'name' => 'Rating System',
        'description' => '1-5 star customer ratings',
        'required' => false,
    ],
];
