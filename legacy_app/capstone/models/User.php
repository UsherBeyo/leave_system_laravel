<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($email, $password, $role, $token) {
        $query = "INSERT INTO $this->table 
                  (email, password, role, activation_token) 
                  VALUES (:email, :password, :role, :token)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role,
            ':token' => $token
        ]);
    }

    public function login($email, $password) {
        $query = "SELECT * FROM $this->table WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password']) && $user['is_active']) {
            return $user;
        }
        return false;
    }

    public function activate($token) {
        $query = "UPDATE $this->table 
                  SET is_active = 1, activation_token = NULL 
                  WHERE activation_token = :token";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':token' => $token]);
    }
}