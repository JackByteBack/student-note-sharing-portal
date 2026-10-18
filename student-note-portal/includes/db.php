<?php
/**
 * DB connection — PDO only, real prepared statements (no emulation).
 * XAMPP defaults: root with empty password. Change here if yours differs.
 */
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'note_portal';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // MySQL not running / wrong creds — a clean message instead of a stack trace.
        http_response_code(503);
        die('Database unavailable — is MySQL running and the note_portal schema imported? (Server said: ' . htmlspecialchars($e->getMessage()) . ')');
    }
    return $pdo;
}

/** Shorthand: prepare + execute + return the statement. */
function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
