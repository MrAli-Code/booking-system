<?php
/**
 * Generate License Key API
 * POST /api/generate
 * 
 * Input: JSON { customer_id, customer_name, plan, domain, features, expiry_days }
 * Output: JSON { success, license_key, expires_at }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Auth check
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if ($token !== hash('sha256', LS_ADMIN_USER . ':' . LS_ADMIN_PASS)) {
    // Allow session-based auth
    session_start();
    if (!isset($_SESSION['ls_user'])) {
        json_response(['error' => 'Unauthorized'], 401);
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['error' => 'Invalid JSON input'], 400);
}

$customerId = $input['customer_id'] ?? generate_license_id();
$customerName = $input['customer_name'] ?? 'Customer';
$plan = $input['plan'] ?? 'basic';
$domain = $input['domain'] ?? '*';
$features = $input['features'] ?? null;
$expiryDays = $input['expiry_days'] ?? LS_DEFAULT_EXPIRY_DAYS;

// Validate plan
$plans = $GLOBALS['ls_plans'];
if (!isset($plans[$plan])) {
    json_response(['error' => "Invalid plan: {$plan}"], 400);
}

// Build payload
$payload = [
    'customer_id' => (string) $customerId,
    'customer_name' => $customerName,
    'plan' => $plan,
    'domain' => $domain,
    'issued_at' => date('Y-m-d H:i:s'),
    'version' => '1.0',
];

// Features
if ($features) {
    $payload['features'] = $features;
} else {
    $payload['features'] = $plans[$plan]['features'];
}

// Expiry
if (isset($plans[$plan]['never_expires']) && $plans[$plan]['never_expires']) {
    $payload['expires_at'] = null;
} else {
    $payload['expires_at'] = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
}

// Encrypt payload
$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(LS_CIPHER));
$encrypted = openssl_encrypt(
    json_encode($payload),
    LS_CIPHER,
    hash('sha256', LS_ENCRYPTION_KEY, true),
    0,
    $iv
);

$combined = base64_encode($iv) . ':' . $encrypted;
$signature = substr(hash('sha256', $combined . LS_SIGNING_KEY), 0, 8);
$encoded = base64_encode($combined);

// Format key: LCS-XXXX-XXXX-XXXX.XXXXXXXX
$chunks = str_split($encoded, 4);
$licenseKey = 'LCS-' . implode('-', $chunks) . '.' . $signature;

// Store license
$licenses = load_licenses();
$license = [
    'id' => generate_license_id(),
    'license_key' => $licenseKey,
    'customer_id' => $customerId,
    'customer_name' => $customerName,
    'plan' => $plan,
    'domain' => $domain,
    'features' => $payload['features'],
    'status' => 'active',
    'issued_at' => $payload['issued_at'],
    'expires_at' => $payload['expires_at'],
    'activated_at' => null,
    'last_validated_at' => null,
    'validation_count' => 0,
    'metadata' => $input['metadata'] ?? [],
    'created_at' => date('Y-m-d H:i:s'),
];
$licenses[] = $license;
save_licenses($licenses);

log_audit('license_generated', "Key: {$licenseKey}, Plan: {$plan}, Customer: {$customerName}");

json_response([
    'success' => true,
    'license_key' => $licenseKey,
    'plan' => $plan,
    'expires_at' => $payload['expires_at'],
    'features' => $payload['features'],
    'customer_id' => $customerId,
]);
