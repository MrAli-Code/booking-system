<?php
/**
 * License Server Configuration
 * 
 * CHANGE THESE VALUES BEFORE DEPLOYMENT
 */

define('LS_ADMIN_USER', 'admin');
define('LS_ADMIN_PASS', password_hash('admin123', PASSWORD_DEFAULT));
define('LS_ENCRYPTION_KEY', 'change-this-to-a-random-32-char-key!!');
define('LS_SIGNING_KEY', 'change-this-signing-key-too-please!!');
define('LS_CIPHER', 'aes-256-cbc');

// License defaults
define('LS_DEFAULT_EXPIRY_DAYS', 365);
define('LS_TRIAL_DAYS', 14);
define('LS_MAX_DOMAINS', 5);

// Available plans
$GLOBALS['ls_plans'] = [
    'basic' => [
        'name' => 'Basic',
        'price' => 99,
        'features' => ['booking', 'payment', 'crm', 'notification'],
        'max_tenants' => 1,
        'max_bookings' => 500,
    ],
    'pro' => [
        'name' => 'Professional',
        'price' => 199,
        'features' => ['booking', 'payment', 'crm', 'wallet', 'referral', 'notification', 'report', 'rating'],
        'max_tenants' => 1,
        'max_bookings' => 2000,
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price' => 499,
        'features' => '*',
        'max_tenants' => 999,
        'max_bookings' => 999999,
    ],
    'lifetime' => [
        'name' => 'Lifetime',
        'price' => 999,
        'features' => '*',
        'max_tenants' => 999,
        'max_bookings' => 999999,
        'never_expires' => true,
    ],
];
