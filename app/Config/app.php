<?php
return [
    'name' => 'Booking System',
    'version' => '1.0.0',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => (bool) (getenv('APP_DEBUG') ?: false),
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => 'Asia/Tehran',
    'locale' => 'fa',
    'fallback_locale' => 'en',
    'key' => getenv('APP_KEY') ?: '',
    'cipher' => 'AES-256-CBC',
    'mode' => getenv('APP_MODE') ?: 'standalone', // saas, standalone, wordpress, mvp
    'deployment' => getenv('APP_DEPLOYMENT') ?: 'shared', // shared, vps, docker, offline
    'detect_environment' => true,
    'providers' => [
        \BBS\App\Modules\License\LicenseServiceProvider::class,
        \BBS\App\Modules\Booking\BookingServiceProvider::class,
        \BBS\App\Modules\Payment\PaymentServiceProvider::class,
        \BBS\App\Modules\CRM\CRMServiceProvider::class,
        \BBS\App\Modules\Notification\NotificationServiceProvider::class,
        \BBS\App\Modules\Wallet\WalletServiceProvider::class,
    ],
];
