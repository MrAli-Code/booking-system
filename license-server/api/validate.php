<?php
/**
 * Validate License Key API
 * POST /api/validate
 * 
 * Input: JSON { license_key, domain, fingerprint }
 * Output: JSON { valid, plan, expires_at, features, ... }
 */

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['error' => 'Invalid JSON input'], 400);
}

$licenseKey = $input['license_key'] ?? '';
$domain = $input['domain'] ?? $_SERVER['HTTP_HOST'] ?? '';
$fingerprint = $input['fingerprint'] ?? '';

if (empty($licenseKey)) {
    json_response(['valid' => false, 'error' => 'License key is required'], 400);
}

// Find license
$license = get_license_by_key($licenseKey);
if (!$license) {
    json_response(['valid' => false, 'error' => 'License key not found'], 404);
}

// Check status
if ($license['status'] !== 'active') {
    json_response(['valid' => false, 'error' => "License is {$license['status']}"]);
}

// Check expiry
if (!empty($license['expires_at'])) {
    $expires = strtotime($license['expires_at']);
    if ($expires !== false && $expires < time()) {
        json_response(['valid' => false, 'error' => 'License has expired', 'expires_at' => $license['expires_at']]);
    }
}

// Validate domain
if ($license['domain'] !== '*' && !empty($domain)) {
    $allowedDomains = explode(',', $license['domain']);
    $allowedDomains = array_map('trim', $allowedDomains);
    $matched = false;
    foreach ($allowedDomains as $d) {
        if ($d === $domain || strpos($domain, $d) !== false || strpos($d, $domain) !== false) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        json_response(['valid' => false, 'error' => 'License not valid for this domain']);
    }
}

// Update validation stats
$licenses = load_licenses();
foreach ($licenses as &$lic) {
    if ($lic['id'] === $license['id']) {
        $lic['last_validated_at'] = date('Y-m-d H:i:s');
        $lic['validation_count'] = ($lic['validation_count'] ?? 0) + 1;
        if (empty($lic['activated_at'])) {
            $lic['activated_at'] = date('Y-m-d H:i:s');
        }
        break;
    }
}
save_licenses($licenses);

log_audit('license_validated', "Key: {$licenseKey}, Domain: {$domain}");

json_response([
    'valid' => true,
    'plan' => $license['plan'],
    'features' => $license['features'],
    'customer_id' => $license['customer_id'],
    'customer_name' => $license['customer_name'],
    'expires_at' => $license['expires_at'],
    'issued_at' => $license['issued_at'],
    'activated_at' => $license['activated_at'] ?? null,
]);
