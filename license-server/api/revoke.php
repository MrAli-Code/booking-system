<?php
/**
 * Revoke License API
 * POST /api/revoke
 * 
 * Input: JSON { license_key, reason }
 * Output: JSON { success, status }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if ($token !== hash('sha256', LS_ADMIN_USER . ':' . LS_ADMIN_PASS)) {
    session_start();
    if (!isset($_SESSION['ls_user'])) {
        json_response(['error' => 'Unauthorized'], 401);
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$licenseKey = $input['license_key'] ?? '';
$reason = $input['reason'] ?? 'No reason provided';

$license = get_license_by_key($licenseKey);
if (!$license) {
    json_response(['error' => 'License not found'], 404);
}

$licenses = load_licenses();
foreach ($licenses as &$lic) {
    if ($lic['id'] === $license['id']) {
        $lic['status'] = 'revoked';
        $lic['revoked_at'] = date('Y-m-d H:i:s');
        $lic['revoke_reason'] = $reason;
        break;
    }
}
save_licenses($licenses);

log_audit('license_revoked', "Key: {$licenseKey}, Reason: {$reason}");

json_response([
    'success' => true,
    'status' => 'revoked',
    'license_key' => $licenseKey,
    'revoked_at' => date('Y-m-d H:i:s'),
]);
