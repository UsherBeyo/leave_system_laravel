<?php

class Auth {
    public static function appBasePath(): string {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = rtrim(dirname($scriptName), '/');
        if (preg_match('#/(views|controllers|helpers|models|api|scripts)$#', $dir)) {
            $dir = rtrim(dirname($dir), '/');
        }
        if ($dir === '' || $dir === '.') {
            return '';
        }
        return $dir;
    }

    public static function appUrl(string $path = ''): string {
        $base = self::appBasePath();
        $path = ltrim($path, '/');
        if ($path == '') {
            return $base !== '' ? $base . '/' : '/';
        }
        return ($base !== '' ? $base : '') . '/' . $path;
    }

    public static function normalizeLocation(string $location): string {
        $location = trim($location);
        if ($location === '') {
            return $location;
        }
        if (preg_match('#^(?:https?:)?//#i', $location) || str_starts_with($location, '/')) {
            return $location;
        }

        $normalized = str_replace('\\', '/', $location);

        if (preg_match('#(?:^|/)(?:views/)?([A-Za-z0-9_-]+)\.php(?:(\?.*)?)$#', $normalized, $matches)) {
            return self::appUrl($matches[1]) . ($matches[2] ?? '');
        }

        if ($normalized === 'login' || $normalized === 'dashboard') {
            return self::appUrl($normalized);
        }

        return $location;
    }

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function sendNoCacheHeaders(): void {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        }
    }

    public static function requireLogin(string $redirect = 'login.php'): void {
        self::startSession();
        self::sendNoCacheHeaders();

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . self::normalizeLocation($redirect));
            exit();
        }
    }

    public static function requireRole($roles) {
        self::startSession();
        self::sendNoCacheHeaders();
        if (!in_array($_SESSION['role'] ?? '', (array)$roles, true)) {
            header('HTTP/1.1 403 Forbidden');
            die('Access denied');
        }
    }

    public static function checkCsrf($token) {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function generateCsrf() {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
