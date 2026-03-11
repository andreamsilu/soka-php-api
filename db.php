<?php
/**
 * PDO database connection helper.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_pdo()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Database connection failed']);
        exit;
    }

    return $pdo;
}

