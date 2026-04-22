<?php

class LeaveType {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Fetch a leave type by id or name
     */
    public function get($identifier) {
        if (is_numeric($identifier)) {
            $stmt = $this->conn->prepare("SELECT * FROM leave_types WHERE id = ?");
            $stmt->execute([$identifier]);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM leave_types WHERE name = ?");
            $stmt->execute([$identifier]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Return all leave types (for dropdowns etc.)
     */
    public function all() {
        $stmt = $this->conn->query("SELECT * FROM leave_types ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert a new leave type (admin functionality)
     */
    public function create($data) {
        $stmt = $this->conn->prepare(
            "INSERT INTO leave_types (name, deduct_balance, requires_approval, max_days_per_year, auto_approve)
             VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $data['name'],
            $data['deduct_balance'],
            $data['requires_approval'],
            $data['max_days_per_year'],
            $data['auto_approve']
        ]);
    }
}
