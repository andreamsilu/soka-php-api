<?php
/**
 * Basic configuration for the SOKA PHP API.
 *
 * IMPORTANT:
 * - Set your real DB credentials here.
 * - Keep API_SECURITY_KEY in sync with NEXT_PUBLIC_API_SECURITY_KEY in the Next.js app.
 */

declare(strict_types=1);

// Database connection settings (update to your environment)
const DB_DSN  = 'mysql:host=127.0.0.1;port=3306;dbname=soka;charset=utf8mb4';
const DB_USER = 'soka_user';
const DB_PASS = 'soka_password';

// Security key shared with the frontend (sent as `securityKey` header)
const API_SECURITY_KEY = 'change-this-to-a-strong-secret';

