<?php
/**
 * User repository helper functions.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function normalize_msisdn(string $msisdn): string
{
    return preg_replace('/[\s\-\(\)]/', '', $msisdn) ?? $msisdn;
}

function is_valid_msisdn(string $msisdn): bool
{
    $normalized = normalize_msisdn($msisdn);

    // Basic international number pattern: optional +, 10–15 digits
    return $normalized !== '' && preg_match('/^\+?\d{10,15}$/', $normalized) === 1;
}

function find_user_by_msisdn(string $msisdn): ?array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE msisdn = :msisdn LIMIT 1');
    $stmt->execute(['msisdn' => $msisdn]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

function create_user_with_msisdn(string $msisdn): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO users (msisdn, created_at, updated_at)
         VALUES (:msisdn, NOW(), NOW())'
    );
    $stmt->execute([
        'msisdn'    => $msisdn,
    ]);

    $id = (int) $pdo->lastInsertId();
    return [
        'id'              => $id,
        'msisdn'          => $msisdn,
    ];
}

function build_user_payload(array $userRow): array
{
    return [
        'id'      => (string)($userRow['id'] ?? ''),
        'msisdn'  => (string)($userRow['msisdn'] ?? ''),
    ];
}

