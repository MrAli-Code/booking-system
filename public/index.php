<?php
/**
 * Booking System - Entry Point
 * 
 * Supports: SaaS Multi-Tenant, Standalone, WordPress Plugin, MVP/Lite modes
 * Hosting: Shared (cPanel), VPS, Docker, Offline
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('BBS_START', microtime(true));
define('BBS_ROOT', dirname(__DIR__));
define('BBS_PUBLIC', __DIR__);
define('BBS_CORE', BBS_ROOT . '/core');
define('BBS_APP', BBS_ROOT . '/app');
define('BBS_MODULES', BBS_ROOT . '/modules');

// Autoloader
require_once BBS_CORE . '/App.php';
require_once BBS_CORE . '/Router.php';
require_once BBS_CORE . '/Database.php';
require_once BBS_CORE . '/Model.php';
require_once BBS_CORE . '/Controller.php';
require_once BBS_CORE . '/Request.php';
require_once BBS_CORE . '/Response.php';
require_once BBS_CORE . '/View.php';
require_once BBS_CORE . '/Session.php';
require_once BBS_CORE . '/Middleware.php';
require_once BBS_CORE . '/Validator.php';
require_once BBS_CORE . '/Helpers.php';

use BBS\Core\App;
use BBS\Core\Response;
use BBS\Core\Session;

$app = App::getInstance();

// Load configuration
$app->loadConfig(BBS_APP . '/Config/app.php');
$app->loadConfig(BBS_APP . '/Config/modules.php');
$app->loadConfig(BBS_APP . '/Config/database.php');
$app->loadConfig(BBS_APP . '/Config/license.php');

// Detect environment and mode
$mode = getenv('APP_MODE') ?: detectDeploymentMode();
$app->setMode($mode);

// Auto-configure for detected environment
$env = $app->detectEnvironment();
if ($env['is_shared_hosting'] || $mode === 'mvp') {
    // MVP/Lite optimizations for shared hosting
    ini_set('memory_limit', '128M');
    $app->setConfig('app.debug', false);
    // Disable heavy modules by default
    $modules = $app->config('modules', []);
    foreach (['wallet', 'referral', 'crypto', 'telegram', 'whatsapp', 'bale', 'rubika', 'medical_mode'] as $heavy) {
        if (isset($modules[$heavy])) {
            $modules[$heavy]['enabled'] = false;
        }
    }
    $app->setConfig('modules', $modules);
}

// CORS
Response::cors();

// Session
Session::getInstance()->start();

// Initialize database
try {
    $app->getDatabase()->connect();
} catch (\Exception $e) {
    // Will redirect to installer
}

// Load routes
require_once BBS_APP . '/routes.php';

// License check (skip for installer and API)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$skipPaths = ['/install', '/api/license', '/api/auth', '/api/webhook'];
$shouldCheck = true;
foreach ($skipPaths as $skip) {
    if (strpos($path, $skip) === 0) {
        $shouldCheck = false;
        break;
    }
}

if ($shouldCheck && !$app->isWordPress()) {
    $licenseValid = $app->config('license.valid', false);
    if (!$licenseValid) {
        // Check database license
        $db = $app->getDatabase();
        if ($db) {
            $license = $db->fetch("SELECT * FROM {prefix}licenses WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            if ($license) {
                $app->setConfig('license.valid', true);
                $app->setConfig('license.features', json_decode($license->features ?? '[]', true));
            }
        }
    }
}

// Run application
try {
    $app->run();
} catch (\Exception $e) {
    if ($app->config('app.debug')) {
        Response::json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    } else {
        Response::json(['error' => 'Internal server error'], 500);
    }
}

function detectDeploymentMode(): string
{
    // Auto-detect environment
    if (defined('WPINC')) {
        return 'wordpress';
    }

    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    // Check for shared hosting indicators
    if (strpos($docRoot, '/home/') !== false || strpos($docRoot, '/public_html/') !== false) {
        // Check if it's a low-resource environment
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1' || (int)$memoryLimit <= 128) {
            return 'mvp';
        }
        return 'standalone';
    }

    // Default to standalone
    return 'standalone';
}
