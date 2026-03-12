<?php
/**
 * Basic configuration for the SOKA PHP API.
 *
 * IMPORTANT:
 * - Configure real DB credentials and secrets through environment variables in production.
 * - Keep API_SECURITY_KEY in sync with NEXT_PUBLIC_API_SECURITY_KEY in the Next.js app.
 */

declare(strict_types=1);

// Application environment: "production", "local", "staging", etc.
const APP_ENV = 'local';

// Database connection settings (configured directly, no .env)
const DB_DSN  = 'mysql:host=127.0.0.1;port=3306;dbname=soka_db;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = 'gfmLvsb}qXB4+Ayk';

// Optional API security key (currently unused by this API)
const API_SECURITY_KEY = '0e22006ade7a8c0217575b880084554b7eb4addd4bf9a56931d2d550fda6f6be';

// External SMS provider configuration (configured directly, no .env)
const SMS_API_URL          = 'http://188.64.188.232/SOKATRIVIA-API-TEST/index.php/api/sms/post/web';
const SMS_API_SECURITY_KEY = 'b2dc84400b5cbfd409d798609d4fba75';


