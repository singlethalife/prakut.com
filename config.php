<?php
/**
 * config.php — Prakut CRM & CMS application settings.
 * Modeled after Zoloverse architecture. Supports local XAMPP and environment overrides.
 */

declare(strict_types=1);

/* ---------------- Database Credentials ---------------- */
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_HOST',   getenv('DB_HOST')   ?: 'localhost');
define('DB_PORT',   getenv('DB_PORT')   ?: '3306');
define('DB_NAME',   getenv('DB_NAME')   ?: 'prakut_db');
define('DB_USER',   getenv('DB_USER')   ?: 'root');
define('DB_PASS',   getenv('DB_PASS')   ?: '');

/* ---------------- Security & JWT ---------------- */
define('JWT_SECRET',  getenv('JWT_SECRET')  ?: 'prakut_crm_cms_super_secret_jwt_key_2026_9884306922');
define('SESSION_TTL', (int)(getenv('SESSION_TTL') ?: 86400)); // 24 hours

/* ---------------- Notifications ---------------- */
define('ADMIN_NOTIFY_EMAIL', getenv('ADMIN_NOTIFY_EMAIL') ?: 'contact@prakut.com');
define('APP_NAME', 'Prakut Farm Transformation');
