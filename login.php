<?php
/**
 * POST /login
 * or POST /auth/login
 *
 * Simple login endpoint using MSISDN only.
 * A "logged-in" user is any MSISDN that has at least one row in user_otp
 * with is_used = 1 (i.e. registration OTP was successfully verified).
 *
 * Body JSON or form: { MSISDN: string }
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/UserRepository.php'; // for normalize_msisdn and is_valid_msisdn

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
$msisdn = normalize_msisdn($msisdn);

if ($msisdn === '' || !is_valid_msisdn($msisdn)) {
    http_response_code(400);
    echo json_encode(['message' => 'Valid MSISDN is required']);
    exit;
}

try {
    $pdo = get_pdo();

    // Consider a user "registered/logged-in-eligible" if they have at least
    // one OTP that has been used successfully.
    $stmt = $pdo->prepare(
        'SELECT id, insert_date FROM user_otp WHERE msisdn = :msisdn AND is_used = 1 ORDER BY insert_date DESC LIMIT 1'
    );
    $stmt->execute(['msisdn' => $msisdn]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        http_response_code(404);
        echo json_encode(['message' => 'User not registered']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to check login status']);
    exit;
}

echo json_encode([
    'message' => 'Login successful',
    'user'    => [
        'msisdn'        => $msisdn,
        'registered_at' => $row['insert_date'] ?? null,
    ],
]);

