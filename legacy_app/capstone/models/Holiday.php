<?php
class Holiday {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function all() {
        $stmt = $this->conn->query("SELECT id, holiday_date, description, type FROM holidays ORDER BY holiday_date");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($date, $description, $type = 'Other') {
        $stmt = $this->conn->prepare("INSERT INTO holidays (holiday_date, description, type) VALUES (:d, :desc, :type)");
        return $stmt->execute([':d'=>$date, ':desc'=>$description, ':type'=>$type]);
    }

    public function update($id, $date, $description, $type = 'Other'){
        $stmt = $this->conn->prepare("UPDATE holidays SET holiday_date = ?, description = ?, type = ? WHERE id = ?");
        return $stmt->execute([$date, $description, $type, $id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM holidays WHERE id = ?");
        return $stmt->execute([$id]);
    }
}