<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', '10.0.31.5');
define('DB_USER', 'usat_admin'); 
define('DB_PASS', 'abc_123'); 
define('DB_NAME', 'enrollment_profiling_db'); 

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Only declare functions if they don't exist
if (!function_exists('db_query')) {
    function db_query($query) {
        global $conn;
        $result = $conn->query($query);
        if ($conn->error) {
            error_log("Database Error: " . $conn->error . " in query: " . $query);
            return false;
        }
        return $result;
    }
}

if (!function_exists('db_prepare')) {
    function db_prepare($query) {
        global $conn;
        return $conn->prepare($query);
    }
}

if (!function_exists('db_escape')) {
    function db_escape($string) {
        global $conn;
        return $conn->real_escape_string($string);
    }
}

if (!function_exists('db_get_last_id')) {
    function db_get_last_id() {
        global $conn;
        return $conn->insert_id;
    }
}
?>