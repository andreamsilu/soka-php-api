<?php
/**
 * Front controller for the SOKA PHP API.
 *
 * This file lives in the public/ folder and is the only file
 * that needs to be exposed by the web server as the document root.
 *
 * It routes incoming requests to the appropriate endpoint scripts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Basic CORS support for browser clients (Next.js frontend)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, securityKey, securitykey, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Handle CORS preflight requests early
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Normalize the request path relative to this index.php
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// Strip any base directory (e.g. /SOKATRIVIA-API-TEST) from the URI
$baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($baseDir !== '' && $baseDir !== '/') {
    if (strpos($requestUri, $baseDir) === 0) {
        $requestUri = substr($requestUri, strlen($baseDir));
    }
}

// Strip leading /index.php if present (e.g. /index.php/api/...)
$requestUri = preg_replace('#^/index\.php#', '', $requestUri);

// Ensure it starts with a single slash
if ($requestUri === '' || $requestUri[0] !== '/') {
    $requestUri = '/' . ltrim($requestUri, '/');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Routing table
if ($method === 'POST') {
    switch ($requestUri) {
        // New, clearer auth routes
        case '/register':
        case '/auth/otp/send':
            require __DIR__ . '/../register.php';
            exit;

        case '/login':
        case '/auth/login':
            require __DIR__ . '/../login.php';
            exit;

        case '/auth/otp/verify':
            require __DIR__ . '/../verify_otp.php';
            exit;

        // Subscription proxy route
        case '/subscribe/web':
            require __DIR__ . '/../subscribe.php';
            exit;

        // Backwards-compatible legacy routes
        case '/api/sms/post/web':
        case '/sms/post/web':
            require __DIR__ . '/../register.php';
            exit;

        case '/api/register/post/web':
        case '/register/post/web':
            require __DIR__ . '/../verify_otp.php';
            exit;
    }
}

// Add before fallback
if ($requestUri === '/' && $method === 'GET') {
    header('Content-Type: application/json');

    $dbOk = false;
    try {
        $pdo = get_pdo();
        $dbOk = $pdo !== null;
    } catch (Throwable $e) {
        $dbOk = false;
    }

    echo json_encode([
        'message'        => 'SOKA PHP API is running!',
        'status'         => 'ok',
        'databaseStatus' => $dbOk ? 'connected' : 'error',
    ]);
    exit;
}

// Fallback: 404 JSON response
header('Content-Type: application/json', true, 404);
echo json_encode([
    'message' => 'Not found',
    'path'    => $requestUri,
]);

