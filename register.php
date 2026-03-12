<?php
/**
 * POST /register
 * or POST /auth/otp/send
 * Generate an OTP and send it via SMS (start registration).
 * Body JSON or form: { MSISDN: string }
 *
 * The external SMS API is called with:
 * {
 *   "MSISDN": "<phone_number>",
 *   "text": "<otp>"
 * }
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/UserRepository.php';

/**
 * Send an SMS message to the given MSISDN via external SMS API.
 * The payload format is:
 * {
 *   "MSISDN": "<phone_number>",
 *   "text": "<otp>"
 * }
 */
function send_otp_via_sms(string $msisdn, string $otp): bool
{
    if (!function_exists('curl_init')) {
        error_log('cURL extension is not available; cannot send SMS.');
        return false;
    }

    $payload = [
        'MSISDN' => $msisdn,
        'text'   => $otp,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SMS_API_URL,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'securityKey: ' . SMS_API_SECURITY_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($responseBody === false) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

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

// Use user_otp as the only source of truth:
// if there is already a used OTP for this msisdn, treat the user as registered
// and do NOT send another OTP.
try {
    $pdo = get_pdo();

    $checkStmt = $pdo->prepare(
        'SELECT id FROM user_otp WHERE msisdn = :msisdn AND is_used = 1 LIMIT 1'
    );
    $checkStmt->execute(['msisdn' => $msisdn]);
    $isRegistered = $checkStmt->fetch() !== false;

    if ($isRegistered) {
        http_response_code(200);
        echo json_encode([
            'message'    => 'User already registered',
            'registered' => true,
            'user'       => ['msisdn' => $msisdn],
        ]);
        exit;
    }

    // Generate a 6-digit OTP for new registration
    $otp = (string) random_int(100000, 999999);

    // Store OTP in MySQL table user_otp
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

// Send the OTP via external SMS provider
$smsSent = send_otp_via_sms($msisdn, $otp);

echo json_encode([
    'message'    => $smsSent ? 'OTP sent successfully' : 'OTP generated, but SMS sending may have failed',
    'registered' => false,
    'user'       => ['msisdn' => $msisdn],
]);

