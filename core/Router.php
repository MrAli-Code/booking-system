<?php
namespace BBS\Core;

class Router
{
    private array $routes = [];
    private array $groupAttributes = [];
    private array $middleware = [];
    private ?Request $request = null;

    public function __construct()
    {
        $this->request = new Request();
    }

    public function get(string $uri, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $handler, $middleware);
    }

    public function post(string $uri, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $uri, $handler, $middleware);
    }

    public function put(string $uri, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $uri, $handler, $middleware);
    }

    public function patch(string $uri, $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $uri, $handler, $middleware);
    }

    public function delete(string $uri, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $uri, $handler, $middleware);
    }

    public function any(string $uri, $handler, array $middleware = []): void
    {
        $this->addRoute('ANY', $uri, $handler, $middleware);
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousGroup = $this->groupAttributes;
        $this->groupAttributes = array_merge_recursive($this->groupAttributes, $attributes);
        $callback($this);
        $this->groupAttributes = $previousGroup;
    }

    public function middleware(string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    private function addRoute(string $method, string $uri, $handler, array $middleware): void
    {
        $prefix = $this->groupAttributes['prefix'] ?? '';
        $uri = $prefix . '/' . trim($uri, '/');
        $uri = '/' . trim($uri, '/');

        $routeMiddleware = array_merge(
            $this->middleware,
            $this->groupAttributes['middleware'] ?? [],
            $middleware
        );
        $this->middleware = [];

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'handler' => $handler,
            'middleware' => $routeMiddleware,
            'name' => null,
        ];
    }

    public function dispatch(): void
    {
        $method = $this->request->method();
        $uri = $this->request->path();

        $route = $this->matchRoute($method, $uri);
        if (!$route) {
            Response::json(['error' => 'Not Found', 'message' => 'Route not found'], 404);
            return;
        }

        // Run global middleware
        foreach (App::getInstance()->getMiddleware() as $mw) {
            $this->executeMiddleware($mw);
        }

        // Run route middleware
        foreach ($route['middleware'] as $mw) {
            $result = $this->executeMiddleware($mw);
            if ($result === false) return;
        }

        $this->executeHandler($route['handler'], $route['params']);
    }

    private function matchRoute(string $method, string $uri): ?array
    {
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) continue;

            $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $route['params'] = $params;
                return $route;
            }
        }

        return null;
    }

    private function executeMiddleware(string $middleware): mixed
    {
        if (class_exists($middleware)) {
            $instance = new $middleware();
            if ($instance instanceof Middleware) {
                return $instance->handle($this->request, function () {});
            }
        }
        return true;
    }

    private function executeHandler($handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($this->request, $params);
            return;
        }

        if (is_string($handler)) {
            $parts = explode('@', $handler);
            $controllerClass = $parts[0];
            $method = $parts[1] ?? 'index';

            if (!class_exists($controllerClass)) {
                $controllerClass = 'BBS\\App\\Controllers\\' . $controllerClass;
            }

            if (class_exists($controllerClass)) {
                $controller = App::getInstance()->make($controllerClass);
                if ($controller instanceof Controller) {
                    $controller->callAction($method, [$this->request, $params]);
                } else {
                    $controller->$method($this->request, $params);
                }
            }
        }
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
