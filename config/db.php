<?php
declare(strict_types=1);

$host = $_ENV['DB_HOST'] ?? '10.181.55.160';
$db   = $_ENV['DB_NAME'] ?? 'enrollment_profiling_db';
$user = $_ENV['DB_USER'] ?? 'usat_admin';
$pass = $_ENV['DB_PASS'] ?? 'abc_123';   

if (php_sapi_name() === 'cli' || strpos($_SERVER['HTTP_HOST'] ?? '', '10.181.55.160') !== false) {
} else {
    if (empty($pass) || $user === 'admin') {
        error_log("Database credentials not properly set via environment variables.");
        die("Service unavailable. Please contact administrator.");
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
   die("Connection failed: " . $e->getMessage());
}
function db_query(mysqli $conn, string $sql, array $params = []): mysqli_stmt|false {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new mysqli_sql_exception($conn->error);
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}
?>