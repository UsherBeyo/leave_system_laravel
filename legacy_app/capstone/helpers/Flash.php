<?php
require_once __DIR__ . '/Auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('flash_normalize_type')) {
    function flash_normalize_type(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $type, string $message): void
    {
        $_SESSION['flash_messages'] = $_SESSION['flash_messages'] ?? [];
        $_SESSION['flash_messages'][] = [
            'type' => flash_normalize_type($type),
            'message' => trim($message),
        ];
    }
}

if (!function_exists('flash_get_all')) {
    function flash_get_all(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return is_array($messages) ? $messages : [];
    }
}

if (!function_exists('flash_redirect')) {
    function flash_redirect(string $location, ?string $type = null, ?string $message = null): void
    {
        if ($type !== null && $message !== null && trim($message) !== '') {
            flash_set($type, $message);
        }
        header('Location: ' . Auth::normalizeLocation($location));
        exit();
    }
}
