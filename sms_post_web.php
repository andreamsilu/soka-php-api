<?php
/**
 * POST /sms/post/web
 * Request OTP for a phone number.
 * Expects header: securityKey
 * Body JSON or form: { MSISDN: string, text?: string }
 * - If "text" is provided, the OTP will be injected into that text by replacing
 *   "{otp}" or "{OTP}" tokens, or appended if no token is present.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/UserRepository.php';

/**
 * Send an SMS message to the given MSISDN via external SMS API.
 */
function send_otp_via_sms(string $msisdn, string $messageText): bool
{
    $payload = [
        'msisdn' => $msisdn,
        'text'   => $messageText,
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
$msisdn = normalize_msisdn($msisdn);

if ($msisdn === '' || !is_valid_msisdn($msisdn)) {
    http_response_code(400);
    echo json_encode(['message' => 'Valid MSISDN is required']);
    exit;
}

// Generate a 6-digit OTP
$otp = (string) random_int(100000, 999999);

// Determine SMS text (either provided by caller or default)
$textTemplate = isset($data['text']) ? (string)$data['text'] : '';
if ($textTemplate !== '') {
    // Replace placeholder tokens if present; otherwise append OTP.
    $smsText = str_replace(['{otp}', '{OTP}'], $otp, $textTemplate);
    if ($smsText === $textTemplate) {
        $smsText .= ' ' . $otp;
    }
} else {
    $smsText = 'Your OTP code is ' . $otp;
}

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

// Send the OTP via external SMS provider
$smsSent = send_otp_via_sms($msisdn, $smsText);

$response = [
    'message' => $smsSent ? 'OTP sent successfully' : 'OTP generated, but SMS sending may have failed',
];

// Only expose OTP in non-production environments for testing purposes.
if (defined('APP_ENV') && APP_ENV !== 'production') {
    $response['otp'] = $otp;
}

echo json_encode($response);

