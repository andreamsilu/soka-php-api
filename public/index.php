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
        case '/api/sms/post/web':
        case '/sms/post/web':
            require __DIR__ . '/../sms_post_web.php';
            exit;

        case '/api/register/post/web':
        case '/register/post/web':
            require __DIR__ . '/../register_post_web.php';
            exit;
    }
}

// Fallback: 404 JSON response
header('Content-Type: application/json', true, 404);
echo json_encode([
    'message' => 'Not found',
    'path'    => $requestUri,
]);

