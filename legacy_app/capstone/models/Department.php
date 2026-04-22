<?php
class Department {
    private $conn;
    private $table = "departments";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name) {
        $query = "INSERT INTO $this->table (name) VALUES (:name)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':name' => $name]);
    }

    public function getAll() {
        $query = "SELECT * FROM $this->table ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM $this->table WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name) {
        $query = "UPDATE $this->table SET name = :name WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':name' => $name, ':id' => $id]);
    }

    public function delete($id) {
        // Check if department is used
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Cannot delete if used
        }
        $query = "DELETE FROM $this->table WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

    public function deactivate($id) {
        // For now, just delete if not used, or perhaps add is_active field later
        return $this->delete($id);
    }
}