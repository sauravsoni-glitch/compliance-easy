<?php
namespace App\Core;

class Router
{
    private array $routes;
    private string $method;
    private string $path;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->normalizePath($_SERVER['REQUEST_URI'] ?? '/');
    }

    private function normalizePath(string $uri): string
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';
        $base = $this->getBasePath();
        if ($base !== '' && $base !== '/' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        return ($uri !== '' && $uri[0] !== '/') ? '/' . $uri : $uri;
    }

    private function getBasePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        return str_replace('\\', '/', dirname($script));
    }

    public function dispatch(): array
    {
        $methodRoutes = $this->routes[$this->method] ?? [];
        foreach ($methodRoutes as $route => $handler) {
            $params = $this->match($route);
            if ($params !== null) {
                return [$handler[0], $handler[1], $params];
            }
        }
        return ['ErrorController', 'notFound', []];
    }

    private function match(string $route): ?array
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $route);
        $pattern = '#^' . $pattern . '$#';
        if (preg_match($pattern, $this->path, $matches)) {
            array_shift($matches);
            $paramNames = [];
            preg_match_all('/\{([a-zA-Z_]+)\}/', $route, $paramNames);
            $paramNames = $paramNames[1] ?? [];
            $params = array_combine($paramNames, $matches);
            return $params ?: [];
        }
        return null;
    }
}
