<?php
use BBS\Core\App;
use BBS\Core\Response;
use BBS\App\Controllers\Public\HomeController;
use BBS\App\Controllers\Public\BookingController as PublicBookingController;
use BBS\App\Controllers\Public\TrackingController;
use BBS\App\Controllers\Api\AuthController;
use BBS\App\Controllers\Api\BookingController as ApiBookingController;
use BBS\App\Controllers\Api\PaymentController as ApiPaymentController;
use BBS\App\Controllers\Api\WebhookController;
use BBS\App\Controllers\Admin\DashboardController;
use BBS\App\Controllers\Admin\BookingController as AdminBookingController;
use BBS\App\Controllers\Admin\CustomerController;
use BBS\App\Controllers\Admin\PaymentController as AdminPaymentController;
use BBS\App\Controllers\Admin\SettingsController;
use BBS\App\Controllers\Admin\LicenseController as AdminLicenseController;
use BBS\App\Middleware\AuthMiddleware;
use BBS\App\Middleware\LicenseMiddleware;
use BBS\App\Middleware\CorsMiddleware;
use BBS\App\Middleware\TenantMiddleware;

$router = App::getInstance()->getRouter();

// ============================================================
// PUBLIC ROUTES
// ============================================================

// Home / Landing
$router->get('/', [HomeController::class, 'index']);
$router->get('/booking', [PublicBookingController::class, 'index']);
$router->get('/booking/services', [PublicBookingController::class, 'getServices']);
$router->get('/booking/slots', [PublicBookingController::class, 'getSlots']);
$router->post('/booking/submit', [PublicBookingController::class, 'submit']);
$router->get('/booking/confirm/{code}', [PublicBookingController::class, 'confirm']);

// Tracking
$router->get('/track/{code}', [TrackingController::class, 'index']);

// ============================================================
// INSTALLER ROUTES
// ============================================================

$router->get('/install', function() {
    require_once BBS_ROOT . '/installer/index.php';
});
$router->post('/install/step1', function() {
    require_once BBS_ROOT . '/installer/index.php';
});
$router->get('/install-step/{step}', function($request, $params) {
    $_GET['step'] = $params['step'];
    require_once BBS_ROOT . '/installer/index.php';
});
$router->post('/install/run', function() {
    require_once BBS_ROOT . '/installer/run.php';
});

// ============================================================
// API ROUTES (v1)
// ============================================================

$router->group(['prefix' => '/api/v1', 'middleware' => [CorsMiddleware::class]], function($router) {
    // Auth
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/register', [AuthController::class, 'register']);
    $router->post('/auth/logout', [AuthController::class, 'logout']);
    $router->post('/auth/refresh', [AuthController::class, 'refresh']);

    // Public Booking API
    $router->get('/services', [ApiBookingController::class, 'listServices']);
    $router->get('/specialists', [ApiBookingController::class, 'listSpecialists']);
    $router->get('/slots', [ApiBookingController::class, 'getAvailableSlots']);
    $router->post('/bookings', [ApiBookingController::class, 'create']);
    $router->get('/bookings/{code}', [ApiBookingController::class, 'track']);

    // Payment
    $router->post('/payments/process', [ApiPaymentController::class, 'process']);
    $router->get('/payments/callback/{gateway}', [ApiPaymentController::class, 'callback']);
    $router->get('/payments/gateways', [ApiPaymentController::class, 'getGateways']);

    // Webhooks
    $router->post('/webhook/payment/{gateway}', [WebhookController::class, 'handle']);
    $router->post('/webhook/sms', [WebhookController::class, 'smsStatus']);

    // Licensed routes (require auth)
    $router->group(['middleware' => [AuthMiddleware::class, LicenseMiddleware::class]], function($router) {
        $router->get('/customer/profile', [ApiBookingController::class, 'profile']);
        $router->get('/customer/bookings', [ApiBookingController::class, 'myBookings']);
        $router->post('/customer/bookings/{id}/cancel', [ApiBookingController::class, 'cancel']);

        // Wallet
        $router->get('/wallet/balance', [ApiPaymentController::class, 'walletBalance']);
        $router->get('/wallet/transactions', [ApiPaymentController::class, 'walletTransactions']);
    });

    // License
    $router->post('/license/validate', [AuthController::class, 'validateLicense']);
    $router->get('/license/info', [AuthController::class, 'licenseInfo']);
});

// ============================================================
// ADMIN ROUTES
// ============================================================

$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class, LicenseMiddleware::class]], function($router) {
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Bookings
    $router->get('/bookings', [AdminBookingController::class, 'index']);
    $router->get('/bookings/{id}', [AdminBookingController::class, 'show']);
    $router->post('/bookings/{id}/approve', [AdminBookingController::class, 'approve']);
    $router->post('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);
    $router->post('/bookings/{id}/confirm-payment', [AdminBookingController::class, 'confirmPayment']);
    $router->get('/bookings/export', [AdminBookingController::class, 'export']);

    // Customers
    $router->get('/customers', [CustomerController::class, 'index']);
    $router->get('/customers/{id}', [CustomerController::class, 'show']);
    $router->post('/customers/{id}/add-wallet', [CustomerController::class, 'addWallet']);
    $router->get('/customers/export', [CustomerController::class, 'export']);

    // Payments
    $router->get('/payments', [AdminPaymentController::class, 'index']);
    $router->get('/payments/{id}', [AdminPaymentController::class, 'show']);
    $router->post('/payments/{id}/verify', [AdminPaymentController::class, 'verify']);
    $router->post('/payments/{id}/refund', [AdminPaymentController::class, 'refund']);

    // Settings
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings', [SettingsController::class, 'update']);
    $router->post('/settings/branding', [SettingsController::class, 'updateBranding']);
    $router->post('/settings/gateways', [SettingsController::class, 'updateGateways']);
    $router->post('/settings/notifications', [SettingsController::class, 'updateNotifications']);

    // License
    $router->get('/license', [AdminLicenseController::class, 'index']);
    $router->post('/license/activate', [AdminLicenseController::class, 'activate']);

    // SaaS Admin only
    $router->get('/tenants', [\BBS\App\Controllers\Admin\TenantController::class, 'index']);
    $router->post('/tenants', [\BBS\App\Controllers\Admin\TenantController::class, 'create']);
    $router->post('/tenants/{id}/suspend', [\BBS\App\Controllers\Admin\TenantController::class, 'suspend']);
    $router->post('/tenants/{id}/activate', [\BBS\App\Controllers\Admin\TenantController::class, 'activate']);
});

// ============================================================
// LICENSE SERVER PROXY
// ============================================================

$router->any('/license-server/{action}', function($request, $params) {
    $action = $params['action'] ?? '';
    $allowed = ['validate', 'activate', 'info'];
    if (in_array($action, $allowed)) {
        require_once BBS_ROOT . '/license-server/api/' . $action . '.php';
    } else {
        Response::json(['error' => 'Invalid action'], 404);
    }
});

// ============================================================
// 404 Fallback
// ============================================================

$router->any('/{path}', function() {
    Response::json(['error' => 'Not Found', 'message' => 'The requested resource was not found.'], 404);
});
