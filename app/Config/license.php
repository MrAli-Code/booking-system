<?php
return [
    'valid' => false,
    'key' => getenv('LICENSE_KEY') ?: '',
    'server_url' => getenv('LICENSE_SERVER_URL') ?: 'https://license.yourdomain.com',
    'auto_validate' => true,
    'validation_interval' => 86400, // 24 hours
    'allow_offline' => true,
    'features' => [],
    'payload' => [],
    'trial_days' => 14,
];
