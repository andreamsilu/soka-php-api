<?php
/**
 * Proxy endpoint: POST /subscribe/web
 *
 * Accepts MSISDN from the frontend (JSON or form-encoded),
 * then forwards the request to the external SOKATRIVIA subscription API using
 * server-side credentials (SMS_API_SECURITY_KEY).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/UserRepository.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

// Read body as JSON first; fall back to form data.
$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$msisdn = isset($data['MSISDN']) ? (string) $data['MSISDN'] : '';
$msisdn = normalize_msisdn($msisdn);

if ($msisdn === '') {
    http_response_code(400);
    echo json_encode(['message' => 'MSISDN is required']);
    exit;
}

// Build JSON payload for upstream API
$payload = json_encode(['MSISDN' => $msisdn]);

$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  =>
            "Content-Type: application/json\r\n" .
            // Match Postman header name exactly
            "securitykey: " . SMS_API_SECURITY_KEY . "\r\n",
        'content' => $payload,
        'timeout' => 15,
    ],
];

$context = stream_context_create($opts);
$responseBody = @file_get_contents(
    'http://188.64.188.232/SOKATRIVIA-API-TEST/index.php/api/subscribe/web',
    false,
    $context
);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode(['message' => 'Failed to contact subscription service']);
    exit;
}

// Try to decode JSON; if it fails, wrap raw body.
$decoded = json_decode($responseBody, true);
if (!is_array($decoded)) {
    echo json_encode([
        'status'   => 'UNKNOWN',
        'message'  => 'Subscription response could not be parsed',
        'rawBody'  => $responseBody,
    ]);
    exit;
}

echo json_encode($decoded);

