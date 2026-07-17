<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db !== null) {
        try {
            $db->query('SELECT 1');
        } catch (Throwable $e) {
            $db = null;
        }
    }
    if ($db === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $db;
}


