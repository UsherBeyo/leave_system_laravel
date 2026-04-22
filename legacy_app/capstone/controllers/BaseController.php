<?php

class BaseController {
    protected $db;

    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $this->db = (new Database())->connect();
        session_start();
    }

    /**
     * Render a view and pass data array to it.
     */
    protected function view($path, $data = []) {
        extract($data);
        include __DIR__ . '/../views/' . $path;
    }

    /**
     * Basic CSRF token generation/checker
     */
    protected function generateCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function checkCsrf($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
