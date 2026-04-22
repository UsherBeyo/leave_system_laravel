<?php

class Router {
    private static $routes = [];

    public static function get($path, $handler) {
        self::addRoute('GET', $path, $handler);
    }

    public static function post($path, $handler) {
        self::addRoute('POST', $path, $handler);
    }

    private static function addRoute($method, $path, $handler) {
        self::$routes[] = compact('method', 'path', 'handler');
    }

    public static function dispatch() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        foreach (self::$routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                if (is_callable($route['handler'])) {
                    call_user_func($route['handler']);
                } elseif (is_string($route['handler'])) {
                    // format "Controller@method"
                    list($controller, $func) = explode('@', $route['handler']);
                    require_once __DIR__ . '/../controllers/' . $controller . '.php';
                    (new $controller())->$func();
                }
                return;
            }
        }
        // not found
        header("HTTP/1.0 404 Not Found");
        echo "404 page not found";
    }
}
