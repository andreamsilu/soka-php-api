<?php
/**
 * POST /sms/post/web
 * Request OTP for a phone number.
 * Expects header: securityKey
 * Body JSON or form: { MSISDN: string }
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

$receivedKey = $_SERVER['HTTP_SECURITYKEY'] ?? '';
if ($receivedKey !== API_SECURITY_KEY) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$msisdn = isset($data['MSISDN']) ? (string)$data['MSISDN'] : '';
$msisdn = preg_replace('/[\s\-\(\)]/', '', $msisdn) ?? $msisdn;

if ($msisdn === '') {
    http_response_code(400);
    echo json_encode(['message' => 'MSISDN is required']);
    exit;
}

// Generate a 6-digit OTP
$otp = (string) random_int(100000, 999999);

// Store OTP in MySQL table user_otp
try {
    $pdo = get_pdo();

    // Optionally mark previous unused OTPs for this msisdn as used
    $markStmt = $pdo->prepare('UPDATE user_otp SET is_used = 1 WHERE msisdn = :msisdn AND is_used = 0');
    $markStmt->execute(['msisdn' => $msisdn]);

    $stmt = $pdo->prepare(
        'INSERT INTO user_otp (msisdn, otp, is_used, insert_date) VALUES (:msisdn, :otp, 0, NOW())'
    );
    $stmt->execute([
        'msisdn' => $msisdn,
        'otp'    => $otp,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to store OTP']);
    exit;
}

// TODO: integrate with real SMS provider here (send $otp to $msisdn)

$response = [
    'message' => 'OTP sent successfully',
    // NOTE: For production, remove this field. It is useful for testing only.
    'otp'     => $otp,
];

echo json_encode($response);

