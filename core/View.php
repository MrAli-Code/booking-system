<?php
namespace BBS\Core;

class View
{
    private static string $basePath = '';
    private static string $extension = '.php';
    private static array $sections = [];
    private static string $currentSection = '';
    private static array $shared = [];

    public static function init(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/\\');
    }

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = []): void
    {
        $data = array_merge(self::$shared, $data);
        $content = self::getContent($view, $data);
        echo $content;
    }

    public static function getContent(string $view, array $data = []): string
    {
        $data = array_merge(self::$shared, $data);
        $file = self::$basePath . '/' . str_replace('.', '/', $view) . self::$extension;

        if (!file_exists($file)) {
            return "<!-- View not found: {$view} -->";
        }

        extract($data);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    public static function section(string $name): void
    {
        self::$currentSection = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        self::$sections[self::$currentSection] = ob_get_clean();
    }

    public static function yield(string $name): string
    {
        return self::$sections[$name] ?? '';
    }

    public static function extends(string $layout): void
    {
        self::$sections['__layout'] = $layout;
    }

    public static function component(string $component, array $data = []): string
    {
        return self::getContent("components/{$component}", $data);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function json($data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function asset(string $path): string
    {
        $base = rtrim(App::getInstance()->config('app.url', ''), '/');
        return "{$base}/assets/{$path}";
    }
}
