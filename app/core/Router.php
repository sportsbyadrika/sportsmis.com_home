<?php
namespace Core;

class Router
{
    private array $routes = [];
    private array $params = [];

    public function add(string $method, string $path, string $handler): void
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => "#^{$pattern}$#",
            'handler' => $handler,
        ];
    }

    public function get(string $path, string $handler): void  { $this->add('GET',  $path, $handler); }
    public function post(string $path, string $handler): void { $this->add('POST', $path, $handler); }

    public function dispatch(string $method, string $uri): void
    {
        $uri = strtok($uri, '?');

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;
            if (!preg_match($route['pattern'], $uri, $matches)) continue;

            $this->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$controllerName, $action] = explode('@', $route['handler']);

            $class = "Controllers\\{$controllerName}";
            $controller = new $class();
            $controller->$action(...array_values($this->params));
            return;
        }

        http_response_code(404);
        require APP_ROOT . '/views/errors/404.php';
    }
}
