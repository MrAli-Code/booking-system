<?php
/**
 * LICENSE SERVER - Lightweight License Management
 * 
 * Runs on separate hosting (PHP 7.4+)
 * Zero dependencies, works on shared hosting
 * 
 * Usage: 
 *   php -S 0.0.0.0:8080 -t license-server
 *   Or deploy to any PHP web host
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('LS_ROOT', __DIR__);
define('LS_DATA', LS_ROOT . '/data');

// Ensure data directory exists
if (!is_dir(LS_DATA)) {
    mkdir(LS_DATA, 0755, true);
}

// Simple router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($method === 'OPTIONS') { http_response_code(204); exit; }

// Load config
require_once LS_ROOT . '/config.php';

// Route
$routes = [
    '/' => 'admin/index.php',
    '/admin' => 'admin/index.php',
    '/admin/login' => 'admin/login.php',
    '/admin/create' => 'admin/create.php',
    '/admin/list' => 'admin/list.php',
    '/admin/revoke' => 'admin/revoke.php',
    '/api/generate' => 'api/generate.php',
    '/api/validate' => 'api/validate.php',
    '/api/revoke' => 'api/revoke.php',
    '/api/info' => 'api/info.php',
    '/api/health' => 'api/health.php',
];

$file = $routes[$uri] ?? null;

if ($file && file_exists(LS_ROOT . '/' . $file)) {
    require_once LS_ROOT . '/' . $file;
} elseif (strpos($uri, '/api/') === 0) {
    json_response(['error' => 'API endpoint not found'], 404);
} else {
    // Serve admin SPA
    require_once LS_ROOT . '/admin/index.php';
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function html_response(string $html, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

function is_authenticated(): bool
{
    session_start();
    return isset($_SESSION['ls_user']);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        header('Location: /admin/login');
        exit;
    }
}

function load_licenses(): array
{
    $file = LS_DATA . '/licenses.json';
    if (!file_exists($file)) return [];
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

function save_licenses(array $licenses): void
{
    $file = LS_DATA . '/licenses.json';
    file_put_contents($file, json_encode($licenses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function generate_license_id(): string
{
    return 'LCS-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
}

function get_license_by_key(string $key): ?array
{
    $licenses = load_licenses();
    foreach ($licenses as $lic) {
        if ($lic['license_key'] === $key || $lic['id'] === $key) {
            return $lic;
        }
    }
    return null;
}

function log_audit(string $action, string $details = ''): void
{
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ];
    $file = LS_DATA . '/audit.log';
    file_put_contents($file, json_encode($log) . "\n", FILE_APPEND | LOCK_EX);
}
