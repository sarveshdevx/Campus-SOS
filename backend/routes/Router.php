<?php
declare(strict_types=1);

namespace CampusSOS\Routes;

use CampusSOS\Services\ResponseService;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        // Convert route path with params {param} to regex pattern
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^{$pattern}$#";

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        // Strip query string and base directory if present
        $cleanUri = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Normalize URI: strip trailing slash except root
        if ($cleanUri !== '/' && str_ends_with($cleanUri, '/')) {
            $cleanUri = rtrim($cleanUri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $cleanUri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (is_array($route['handler'])) {
                    [$class, $action] = $route['handler'];
                    $controller = new $class();
                    call_user_func_array([$controller, $action], array_values($params));
                } else {
                    call_user_func_array($route['handler'], array_values($params));
                }
                return;
            }
        }

        ResponseService::error("Endpoint not found: {$method} {$cleanUri}", 404, null, 'ROUTE_NOT_FOUND');
    }
}

