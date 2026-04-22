<?php
// Run this script once to update the database schema for new leave policy.
// Usage: php migration.php

require_once __DIR__ . '/../config/database.php';

$db = (new Database())->connect();

// Set AUTOCOMMIT to 0 to explicitly control transactions
$db->exec("SET AUTOCOMMIT=0");

try {
    // Create tables FIRST (outside transaction) - they don't need transaction control
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'Other'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Exception $e) {
        // Table might already exist, ignore
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS leave_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            deduct_balance TINYINT(1) NOT NULL DEFAULT 1,
            requires_approval TINYINT(1) NOT NULL DEFAULT 1,
            max_days_per_year DECIMAL(6,3) DEFAULT NULL,
            auto_approve TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Exception $e) {
        // Table might already exist, ignore
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS accrual_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            amount DECIMAL(6,3) NOT NULL,
            date_accrued DATE NOT NULL,
            month_reference VARCHAR(7) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Exception $e) {}

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS leave_balance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            change_amount DECIMAL(6,3) NOT NULL,
            reason VARCHAR(50) NOT NULL,
            leave_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Exception $e) {}

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS accruals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            amount DECIMAL(6,3) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Exception $e) {}

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS budget_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type VARCHAR(50) NOT NULL,
            old_balance DECIMAL(6,3) NOT NULL,
            new_balance DECIMAL(6,3) NOT NULL,
            action VARCHAR(50) NOT NULL,
            leave_request_id INT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Exception $e) {}

    // NOW start transaction for data modifications
    $db->beginTransaction();

    // add new columns if they don't exist
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS annual_balance DECIMAL(6,3) NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS sick_balance DECIMAL(6,3) NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS force_balance INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS position VARCHAR(128) NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS status VARCHAR(64) NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS civil_status VARCHAR(64) NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS entrance_to_duty DATE NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS unit VARCHAR(128) NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS gsis_policy_no VARCHAR(128) NULL");
    $db->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS national_reference_card_no VARCHAR(128) NULL");

    // copy leave_balance to annual_balance for backwards compatibility
    $db->exec("UPDATE employees SET annual_balance = leave_balance WHERE annual_balance = 0");

    // ensure leave_requests has necessary fields
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS leave_type VARCHAR(50) NOT NULL DEFAULT 'Vacational'");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS approved_by INT NULL");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS manager_comments TEXT NULL");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS snapshot_annual_balance DECIMAL(6,3) NULL");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS snapshot_sick_balance DECIMAL(6,3) NULL");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS snapshot_force_balance INT NULL");
    $db->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS leave_type_id INT NULL AFTER leave_type");

    // Clean up and populate leave_types
    $stmt = $db->prepare("DELETE FROM leave_types WHERE name IN ('Annual','Vacation')");
    $stmt->execute();
    
    // Insert defaults
    $stmt = $db->prepare("INSERT IGNORE INTO leave_types (name, deduct_balance, requires_approval, max_days_per_year, auto_approve) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Vacational', 1, 1, null, 0]);
    $stmt->execute(['Sick', 1, 1, null, 0]);
    $stmt->execute(['Emergency', 0, 1, null, 1]);
    $stmt->execute(['Special', 0, 0, null, 1]);

    // Backfill leave_type_id for existing requests
    $db->exec("UPDATE leave_requests lr
                 JOIN leave_types lt ON LOWER(lr.leave_type) = LOWER(lt.name)
                 SET lr.leave_type_id = lt.id");

    $db->commit();
    $db->exec("SET AUTOCOMMIT=1");
    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $db->exec("SET AUTOCOMMIT=1");
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
