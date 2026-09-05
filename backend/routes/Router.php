<?php

namespace App\Routes;

use App\Utils\ViewModel;

class Router
{
    private static array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    public static function get(string $uri, array $action)
    {
        self::$routes['GET'][$uri] = $action;
    }
    public static function post(string $uri, array $action)
    {
        self::$routes['POST'][$uri] = $action;
    }

    public static function put(string $uri, array $action)
    {
        self::$routes['PUT'][$uri] = $action;
    }

    public static function delete(string $uri, array $action)
    {
        self::$routes['DELETE'][$uri] = $action;
    }

    private static function matchRoute(string $pattern, string $uri): array|false
    {
        $regex = preg_replace('/\{[A-Za-z_]+\}/', '([^/]+)', $pattern);
        $regex = "#^" . $regex . "$#";

        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches);
            return $matches;
        }
        return false;
    }
    public static function dispatch()
    {
        $uri = substr($_SERVER["REQUEST_URI"], strlen(dirname($_SERVER["SCRIPT_NAME"])));
        $uri = strtok($uri, '?');
        $method = $_SERVER['REQUEST_METHOD'];

        foreach (self::$routes[$method] as $pattern => $action) {
            $params = self::matchRoute($pattern, $uri);

            if ($params !== false) {
                [$controllerName, $methodName] = $action;
                $fullClassName = "App\\Controllers\\" . $controllerName;

                $controller = new $fullClassName();
                $res = $controller->$methodName(...$params);

                if ($res instanceof ViewModel)
                    $res->render();
                return;
            }
        }

        http_response_code(404);
        echo "No matching route found for $method $uri";
    }
}
