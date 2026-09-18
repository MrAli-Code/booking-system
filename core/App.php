<?php
namespace BBS\Core;

class App
{
    private static ?App $instance = null;
    private array $bindings = [];
    private array $instances = [];
    private array $middleware = [];
    private ?Router $router = null;
    private ?Database $db = null;
    private array $config = [];
    private string $mode = 'standalone';
    private array $modules = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->router = new Router();
        spl_autoload_register([$this, 'autoload']);
    }

    public function autoload(string $class): void
    {
        $prefix = 'BBS\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
        $relative = substr($class, strlen($prefix));
        $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) require_once $file;
    }

    public function loadConfig(string $path): void
    {
        if (file_exists($path)) {
            $this->config = array_merge($this->config, require $path);
        }
    }

    public function config(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) return $default;
            $value = $value[$k];
        }
        return $value;
    }

    public function setConfig(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        foreach ($keys as $k) {
            if (!isset($config[$k])) $config[$k] = [];
            $config = &$config[$k];
        }
        $config = $value;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isSaaS(): bool
    {
        return $this->mode === 'saas';
    }

    public function isWordPress(): bool
    {
        return $this->mode === 'wordpress';
    }

    public function isStandalone(): bool
    {
        return $this->mode === 'standalone';
    }

    public function isMVP(): bool
    {
        return $this->mode === 'mvp';
    }

    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = function () use ($concrete, $abstract) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $concrete();
            }
            return $this->instances[$abstract];
        };
    }

    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        if (isset($this->bindings[$abstract])) {
            $object = $this->bindings[$abstract]($this);
            if ($object instanceof $abstract) {
                $this->instances[$abstract] = $object;
            }
            return $object;
        }
        return $this->resolve($abstract);
    }

    private function resolve(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$constructor) return $reflection->newInstance();

        $params = $constructor->getParameters();
        $dependencies = [];
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                $dependencies[] = null;
            }
        }
        return $reflection->newInstanceArgs($dependencies);
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function addMiddleware(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getDatabase(): ?Database
    {
        if ($this->db === null) {
            $this->db = new Database(
                $this->config('database.host', 'localhost'),
                $this->config('database.name', 'booking'),
                $this->config('database.user', 'root'),
                $this->config('database.pass', ''),
                $this->config('database.driver', 'mysql')
            );
        }
        return $this->db;
    }

    public function setDatabase(Database $db): void
    {
        $this->db = $db;
    }

    public function registerModule(string $name, string $class): void
    {
        $this->modules[$name] = $class;
    }

    public function getModule(string $name): ?string
    {
        return $this->modules[$name] ?? null;
    }

    public function hasModule(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    public function getModules(): array
    {
        return array_keys($this->modules);
    }

    public function run(): void
    {
        $this->getRouter()->dispatch();
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $features = $this->config('license.features', []);
        if (empty($features)) return true;
        return in_array($feature, $features) || $features === '*';
    }

    public function detectEnvironment(): array
    {
        $info = [
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'cli',
            'has_curl' => function_exists('curl_version'),
            'has_mbstring' => function_exists('mb_strlen'),
            'has_pdo_mysql' => in_array('mysql', \PDO::getAvailableDrivers()),
            'has_pdo_sqlite' => in_array('sqlite', \PDO::getAvailableDrivers()),
            'has_openssl' => extension_loaded('openssl'),
            'has_json' => extension_loaded('json'),
            'has_gd' => extension_loaded('gd'),
            'has_gmp' => extension_loaded('gmp'),
            'has_bcmath' => extension_loaded('bcmath'),
            'is_shared_hosting' => $this->isSharedHosting(),
            'memory_limit' => ini_get('memory_limit'),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'max_execution_time' => ini_get('max_execution_time'),
            'safe_mode' => ini_get('safe_mode'),
        ];
        return $info;
    }

    private function isSharedHosting(): bool
    {
        $indicators = [
            '/home/',
            '/public_html/',
            'cpanel',
            'plesk',
            'directadmin',
        ];
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        foreach ($indicators as $indicator) {
            if (strpos($docRoot, $indicator) !== false) return true;
        }
        return false;
    }
}
