<?php
namespace BBS\Core;

class Response
{
    private static array $headers = [];

    public static function json($data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function view(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    public static function with(string $key, $value): void
    {
        Session::getInstance()->flash($key, $value);
    }

    public static function download(string $path, string $filename = null): void
    {
        if (!file_exists($path)) {
            self::json(['error' => 'File not found'], 404);
            return;
        }
        $filename = $filename ?? basename($path);
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public static function file(string $path, string $contentType = null): void
    {
        if (!file_exists($path)) {
            self::json(['error' => 'File not found'], 404);
            return;
        }
        $contentType = $contentType ?? mime_content_type($path);
        header("Content-Type: {$contentType}");
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=31536000');
        readfile($path);
        exit;
    }

    public static function setHeader(string $key, string $value): void
    {
        self::$headers[$key] = $value;
    }

    public static function sendHeaders(): void
    {
        foreach (self::$headers as $key => $value) {
            header("{$key}: {$value}");
        }
    }

    public static function success(string $message = 'Operation successful', array $data = []): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message = 'An error occurred', int $status = 400, array $errors = []): void
    {
        $response = ['success' => false, 'message' => $message];
        if (!empty($errors)) $response['errors'] = $errors;
        self::json($response, $status);
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::json(['success' => false, 'message' => $message], 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::json(['success' => false, 'message' => $message], 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::json(['success' => false, 'message' => $message], 403);
    }

    public static function paginated(array $data, int $total, int $page, int $perPage): void
    {
        self::json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    public static function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override');
        header('Access-Control-Max-Age: 86400');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
