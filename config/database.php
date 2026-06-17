<?php
/**
 * RUMS — Database Configuration
 * Reads from environment variables set by config/environment.php.
 */

// environment.php is loaded first by config.php — env() is available here
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    env('DB_PORT',    '3306'));
define('DB_NAME',    env('DB_NAME',    'rums'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

/**
 * PDO singleton — one connection per request.
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        $is_api = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
        error_log('[DB] Connection failed: ' . $e->getMessage());
        if ($is_api) {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
        } else {
            http_response_code(503);
            echo '<h3>Service temporarily unavailable. Please try again shortly.</h3>';
        }
        exit;
    }

    return $pdo;
}
