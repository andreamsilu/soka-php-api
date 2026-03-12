<?php
/**
 * Basic configuration for the SOKA PHP API.
 *
 * IMPORTANT:
 * - Configure real DB credentials and secrets through environment variables in production.
 * - Keep API_SECURITY_KEY in sync with NEXT_PUBLIC_API_SECURITY_KEY in the Next.js app.
 */

declare(strict_types=1);

// Database connection settings (configured directly, no .env)
const DB_DSN  = 'mysql:host=127.0.0.1;port=3306;dbname=tigosportsDB;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = 'gfmLvsb}qXB4+Ayk';

// External SMS provider configuration (configured directly, no .env)
const SMS_API_URL = 'http://188.64.188.232/SOKATRIVIA-API-TEST/index.php/api/sms/post/web';
const SMS_API_SECURITY_KEY = 'b2dc84400b5cbfd409d798609d4fba75';


