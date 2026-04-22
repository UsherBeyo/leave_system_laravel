<?php

class Employee
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function findWithUser(int $employeeId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT e.*, u.email
            FROM employees e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $employeeId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getBalances(int $employeeId): array
    {
        $stmt = $this->conn->prepare("
            SELECT annual_balance, sick_balance, force_balance
            FROM employees
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'annual_balance' => isset($row['annual_balance']) ? (float)$row['annual_balance'] : 0.0,
            'sick_balance'   => isset($row['sick_balance']) ? (float)$row['sick_balance'] : 0.0,
            'force_balance'  => isset($row['force_balance']) ? (float)$row['force_balance'] : 0.0,
        ];
    }

    public function updateBalances(int $employeeId, ?float $annual, ?float $sick, ?int $force): bool
    {
        $current = $this->getBalances($employeeId);

        $annual = ($annual === null) ? $current['annual_balance'] : $annual;
        $sick   = ($sick === null) ? $current['sick_balance'] : $sick;
        $force  = ($force === null) ? (int)$current['force_balance'] : $force;

        $stmt = $this->conn->prepare("
            UPDATE employees
            SET annual_balance = ?, sick_balance = ?, force_balance = ?
            WHERE id = ?
        ");
        return $stmt->execute([$annual, $sick, $force, $employeeId]);
    }

    public function updateProfile(int $employeeId, array $data): bool
    {
        $allowed = [
            'first_name','middle_name','last_name','department','department_id','position','salary','status','civil_status',
            'entrance_to_duty','unit','gsis_policy_no','national_reference_card_no',
            'manager_id','profile_pic'
        ];

        $sets = [];
        $vals = [];

        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "{$k} = ?";
                $vals[] = $data[$k];
            }
        }

        if (!$sets) return false;

        $vals[] = $employeeId;
        $sql = "UPDATE employees SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($vals);
    }

    public function computeUndertimeDeduction(int $hours, int $minutes): float
    {
        $totalMinutes = max(0, $hours) * 60 + max(0, $minutes);
        return floor(($totalMinutes * 0.002) * 1000) / 1000;
    }

    public function applyUndertimeToAnnual(int $employeeId, float $deduct): array
    {
        $balances = $this->getBalances($employeeId);
        $old = $balances['annual_balance'];
        $new = max(0, $old - max(0, $deduct));

        $stmt = $this->conn->prepare("UPDATE employees SET annual_balance = ? WHERE id = ?");
        $stmt->execute([$new, $employeeId]);

        return ['old' => $old, 'new' => $new];
    }
}