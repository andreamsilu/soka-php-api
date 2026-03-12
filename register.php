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
 * The SMS API in your other system is a classic PHP endpoint and most
 * likely reads values from $_POST and headers, not raw JSON.
 *
 * We therefore send data as application/x-www-form-urlencoded, matching
 * what Postman usually does when configured with form-data/urlencoded.
 */
function send_otp_via_sms(string $msisdn, string $otp): bool
{
    // Payload expected by the SMS API (as form fields):
    // msisdn=<phone_number>&text=<otp>&securityKey=<key>
    $payload = http_build_query([
        'msisdn'      => $msisdn,
        'text'        => $otp,
        'securityKey' => 'b2dc84400b5cbfd409d798609d4fba75',
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  =>
                "Content-Type: application/x-www-form-urlencoded\r\n" .
                "securityKey: b2dc84400b5cbfd409d798609d4fba75\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ];

    $context = stream_context_create($opts);
    // Direct SMS endpoint URL (bypassing config constant for clarity)
    $responseBody = @file_get_contents(
        'http://188.64.188.232/SOKATRIVIA-API-TEST/index.php/api/sms/post/web',
        false,
        $context
    );

    if ($responseBody === false) {
        $error = error_get_last();
        error_log('SMS HTTP error: ' . ($error['message'] ?? 'unknown error'));
        return false;
    }

    // Capture HTTP status code from $http_response_header if available
    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $m)) {
                $httpCode = (int)$m[1];
                break;
            }
        }
    }

    error_log('SMS API response: HTTP ' . $httpCode . ' ' . $responseBody);

    // Fallback: if we got a non-empty body but could not determine HTTP code,
    // treat it as success to avoid false negatives on misconfigured $http_response_header.
    if ($httpCode === 0 && $responseBody !== '') {
        return true;
    }

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

    // Fetch the just-stored OTP from the database to ensure we send
    // exactly what is persisted.
    $selectStmt = $pdo->prepare(
        'SELECT otp FROM user_otp WHERE msisdn = :msisdn ORDER BY insert_date DESC LIMIT 1'
    );
    $selectStmt->execute(['msisdn' => $msisdn]);
    $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
    $otpToSend = $row !== false ? (string)$row['otp'] : $otp;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to store OTP']);
    exit;
}

// Send the OTP via external SMS provider, using the stored value
$smsSent = send_otp_via_sms($msisdn, $otpToSend);

echo json_encode([
    'message'    => $smsSent ? 'OTP sent successfully' : 'OTP generated, but SMS sending may have failed',
    'registered' => false,
    'user'       => ['msisdn' => $msisdn],
]);

