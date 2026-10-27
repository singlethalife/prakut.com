<?php
/**
 * config.php — Prakut CRM & CMS application settings.
 * Modeled after Zoloverse architecture. Supports local XAMPP and environment overrides.
 */

declare(strict_types=1);

/* ---------------- Database Credentials (Supabase PostgreSQL) ---------------- */
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'pgsql');
define('DB_HOST',   getenv('DB_HOST')   ?: 'db.ngkazhrdemwvpubwvaxa.supabase.co');
define('DB_PORT',   getenv('DB_PORT')   ?: '5432');
define('DB_NAME',   getenv('DB_NAME')   ?: 'postgres');
define('DB_USER',   getenv('DB_USER')   ?: 'postgres');
define('DB_PASS',   getenv('DB_PASS')   ?: 'prakut.com@@');

/* ---------------- Security & JWT ---------------- */
define('JWT_SECRET',  getenv('JWT_SECRET')  ?: 'prakut_crm_cms_super_secret_jwt_key_2026_9884306922');
define('SESSION_TTL', (int)(getenv('SESSION_TTL') ?: 86400)); // 24 hours

/* ---------------- Notifications ---------------- */
define('ADMIN_NOTIFY_EMAIL', getenv('ADMIN_NOTIFY_EMAIL') ?: 'contact@prakut.com');
define('APP_NAME', 'Prakut Farm Transformation');
