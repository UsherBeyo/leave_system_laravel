<?php

class ErrorHandler {
    public static function render($message, $code = 500) {
        http_response_code($code);
        echo "<h1>Error</h1><p>" . htmlspecialchars($message) . "</p>";
        exit();
    }

    public static function log($e) {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
