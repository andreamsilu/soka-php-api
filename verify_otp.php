<?php
/**
 * POST /auth/otp/verify
 * Verify OTP for registration (complete registration).
 * Body JSON or form: { MSISDN: string, code: string }
 *
 * NOTE: user_otp is the only table used. A "registered user" is represented
 * by at least one row in user_otp with is_used = 1 for the given MSISDN.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/UserRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$msisdn = isset($data['MSISDN']) ? (string)$data['MSISDN'] : '';
$code   = isset($data['code']) ? (string)$data['code'] : (isset($data['otp']) ? (string)$data['otp'] : '');
$msisdn = normalize_msisdn($msisdn);

if ($msisdn === '' || $code === '') {
    http_response_code(400);
    echo json_encode(['message' => 'MSISDN and code are required']);
    exit;
}

if (!is_valid_msisdn($msisdn)) {
    http_response_code(400);
    echo json_encode(['message' => 'Valid MSISDN is required']);
    exit;
}

if (preg_match('/^\d{6}$/', $code) !== 1) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid code format']);
    exit;
}

// Read latest unused OTP for this msisdn from user_otp
try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT id, otp, insert_date FROM user_otp WHERE msisdn = :msisdn AND is_used = 0 ORDER BY insert_date DESC LIMIT 1'
    );
    $stmt->execute(['msisdn' => $msisdn]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        http_response_code(400);
        echo json_encode(['message' => 'OTP not found or expired']);
        exit;
    }

    $storedOtp   = (string)($row['otp'] ?? '');
    $insertedAt  = strtotime((string)($row['insert_date'] ?? ''));
    $now         = time();
    $maxAgeSecs  = 5 * 60; // 5 minutes

    if ($insertedAt === false || ($now - $insertedAt) > $maxAgeSecs || $storedOtp !== $code) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid or expired OTP']);
        exit;
    }

    // Mark OTP as used
    $upd = $pdo->prepare('UPDATE user_otp SET is_used = 1 WHERE id = :id');
    $upd->execute(['id' => (int)$row['id']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to verify OTP']);
    exit;
}

echo json_encode([
    'message' => 'Registration successful',
    'user'    => [
        'msisdn' => $msisdn,
    ],
]);

