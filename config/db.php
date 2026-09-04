<?php
/**
 * Database connection (PDO / MySQL)
 * Configure these through environment variables in deployment.
 */
define('DB_HOST', getenv('CATBALOGAN_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('CATBALOGAN_DB_NAME') ?: 'catbalogan_ai_assistant');
define('DB_USER', getenv('CATBALOGAN_DB_USER') ?: 'root');
define('DB_PASS', getenv('CATBALOGAN_DB_PASS') ?: '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please check the server configuration.');
}
