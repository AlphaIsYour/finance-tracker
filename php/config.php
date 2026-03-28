<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ai_finance_tracker');

function get_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
    if ($conn->connect_error) {
        die("❌ DB Error: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>