<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{string, string, array, bool}> */
    private array $routes = [];

    /** @param array{class-string, string} $handler */
    public function add(string $method, string $pattern, array $handler, bool $auth = true): void
    {
        $regex = '#^' . preg_replace_callback(
            '#\{(\w+)\}#',
            fn($m) => '(?P<' . $m[1] . '>' . (str_ends_with($m[1], 'slug') ? '[a-z0-9\-]{1,80}' : '[0-9]+') . ')',
            rtrim($pattern, '/') ?: '/'
        ) . '$#';
        $this->routes[] = [$method, $regex, $handler, $auth];
    }

    public function dispatch(Request $req): never
    {
        $allowed = [];
        foreach ($this->routes as [$method, $regex, $handler, $auth]) {
            if (!preg_match($regex, $req->path, $m)) {
                continue;
            }
            if ($method !== $req->method) {
                $allowed[] = $method;
                continue;
            }
            $req->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $user = $auth ? Auth::require($req) : null;
            [$class, $fn] = $handler;
            $result = (new $class())->$fn($req, $user);
            if (is_array($result) && array_key_exists('__status', $result)) {
                Response::json($result['body'], $result['__status']);
            }
            Response::json($result);
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new ApiException(405, 'method_not_allowed', 'Method not allowed.');
        }
        throw new ApiException(404, 'not_found', 'Endpoint not found.');
    }

    /** Helper for controllers that want a non-200 status. */
    public static function status(int $status, mixed $body): array
    {
        return ['__status' => $status, 'body' => $body];
    }
}
