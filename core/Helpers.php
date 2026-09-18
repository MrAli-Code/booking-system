<?php
// ============================================================
// Global Helper Functions
// ============================================================

if (!function_exists('app')) {
    function app(): \BBS\Core\App {
        return \BBS\Core\App::getInstance();
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return app()->config($key, $default);
    }
}

if (!function_exists('request')) {
    function request(): \BBS\Core\Request {
        return new \BBS\Core\Request();
    }
}

if (!function_exists('response')) {
    function response(): \BBS\Core\Response {
        return new \BBS\Core\Response();
    }
}

if (!function_exists('session')) {
    function session(): \BBS\Core\Session {
        return \BBS\Core\Session::getInstance();
    }
}

if (!function_exists('db')) {
    function db(): \BBS\Core\Database {
        return app()->getDatabase();
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): void {
        \BBS\Core\View::render($view, $data);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void {
        \BBS\Core\Response::redirect($url);
    }
}

if (!function_exists('back')) {
    function back(): void {
        \BBS\Core\Response::back();
    }
}

if (!function_exists('json')) {
    function json($data, int $status = 200): void {
        \BBS\Core\Response::json($data, $status);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string {
        $url = config('app.url', '');
        $routes = [
            'home' => '/',
            'booking' => '/booking',
            'booking.confirm' => '/booking/confirm/{code}',
            'track' => '/track/{code}',
            'admin.dashboard' => '/admin',
            'admin.bookings' => '/admin/bookings',
            'admin.customers' => '/admin/customers',
            'admin.settings' => '/admin/settings',
            'admin.license' => '/admin/license',
            'payment.gateway' => '/payment/gateway/{gateway}/{id}',
            'payment.callback' => '/payment/callback/{gateway}',
            'api.services' => '/api/v1/services',
            'api.slots' => '/api/v1/slots',
            'api.bookings' => '/api/v1/bookings',
        ];
        $path = $routes[$name] ?? '/';
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        return rtrim($url, '/') . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        $base = rtrim(config('app.url', ''), '/');
        return "{$base}/assets/{$path}";
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        $session = session();
        if (!$session->has('_csrf_token')) {
            $token = bin2hex(random_bytes(32));
            $session->set('_csrf_token', $token);
        }
        return $session->get('_csrf_token');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = null) {
        return session()->getFlash('_old_' . $key, $default);
    }
}

if (!function_exists('is_active_route')) {
    function is_active_route(string $path): string {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return strpos($current, $path) === 0 ? 'active' : '';
    }
}

if (!function_exists('format_price')) {
    function format_price(float $amount, string $currency = 'IRR'): string {
        $symbols = [
            'IRR' => 'ریال',
            'IRT' => 'تومان',
            'USD' => '$',
            'EUR' => '€',
            'BTC' => '₿',
            'USDT' => 'USDT',
            'ETH' => 'Ξ',
        ];
        $symbol = $symbols[$currency] ?? $currency;
        return number_format($amount) . ' ' . $symbol;
    }
}

if (!function_exists('is_persian')) {
    function is_persian(): bool {
        return config('app.locale', 'fa') === 'fa';
    }
}

if (!function_exists('persian_numbers')) {
    function persian_numbers(string $text): string {
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($en, $fa, $text);
    }
}

if (!function_exists('generate_uuid')) {
    function generate_uuid(): string {
        return \BBS\Core\Model::generateUuid();
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, $default = null) {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!isset($array[$k])) return $default;
            $array = $array[$k];
        }
        return $array;
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $text, int $limit = 100, string $end = '...'): string {
        if (mb_strlen($text) <= $limit) return $text;
        return mb_substr($text, 0, $limit) . $end;
    }
}
