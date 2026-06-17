<?php
/**
 * One-time migration: creates audit_logs table.
 * DELETE THIS FILE after running.
 */
require_once __DIR__ . '/config/config.php';

// Only allow from localhost or with a secret key
$key = $_GET['key'] ?? '';
if ($key !== 'rums_migrate_2026') {
    http_response_code(403);
    die('Forbidden. Pass ?key=rums_migrate_2026');
}

$db = getDB();

$sql = "CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  DEFAULT NULL,
    user_name   VARCHAR(150)  DEFAULT NULL,
    user_role   VARCHAR(50)   DEFAULT NULL,
    action      VARCHAR(100)  NOT NULL,
    module      VARCHAR(100)  NOT NULL,
    record_id   INT UNSIGNED  DEFAULT NULL,
    description TEXT          DEFAULT NULL,
    old_value   TEXT          DEFAULT NULL,
    new_value   TEXT          DEFAULT NULL,
    ip_address  VARCHAR(45)   DEFAULT NULL,
    user_agent  VARCHAR(255)  DEFAULT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_module (module, record_id),
    INDEX idx_audit_user   (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "<b style='color:green'>audit_logs table created (or already exists). Delete this file now.</b>";
} catch (Exception $e) {
    echo "<b style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</b>";
}
