<?php
/**
 * Basic configuration for the SOKA PHP API.
 *
 * IMPORTANT:
 * - Configure real DB credentials and secrets through environment variables in production.
 * - Keep API_SECURITY_KEY in sync with NEXT_PUBLIC_API_SECURITY_KEY in the Next.js app.
 */

declare(strict_types=1);

/**
 * Read an environment variable with a default fallback.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return $value;
}

// Application environment: "production", "local", "staging", etc.
const APP_ENV = 'production' !== (env('APP_ENV') ?? 'production')
    ? (env('APP_ENV') ?? 'production')
    : 'production';

// Database connection settings (can be overridden via environment)
const DB_DSN  = env('DB_DSN', 'mysql:host=127.0.0.1;port=3306;dbname=soka;charset=utf8mb4');
const DB_USER = env('DB_USER', 'soka_user');
const DB_PASS = env('DB_PASS', 'soka_password');

// Security key shared with the frontend (sent as `securityKey` header)
const API_SECURITY_KEY = env('API_SECURITY_KEY', 'change-this-to-a-strong-secret');

// External SMS provider configuration
const SMS_API_URL          = env('SMS_API_URL', 'http://188.64.188.232/SOKATRIVIA-API-TEST/index.php/api/sms/post/web');
const SMS_API_SECURITY_KEY = env('SMS_API_SECURITY_KEY', API_SECURITY_KEY);


