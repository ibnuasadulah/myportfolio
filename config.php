<?php
// =============================================
// GAMEVAULT — KONFIGURASI
// Salin file ini ke config/config.php dan isi nilainya
// =============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'gamevault');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'GameVault');
define('APP_URL',  'http://localhost/gamemarket');
define('APP_KEY',  'ganti-dengan-random-string-32-karakter');

// ShopeePay / Midtrans (gunakan salah satu)
// Midtrans adalah gateway resmi yang support ShopeePay di Indonesia
define('MIDTRANS_SERVER_KEY', 'YOUR_SERVER_KEY');
define('MIDTRANS_CLIENT_KEY', 'YOUR_CLIENT_KEY');
define('MIDTRANS_IS_PRODUCTION', false); // true untuk production

// Alternatif: Xendit
define('XENDIT_SECRET_KEY', 'YOUR_XENDIT_SECRET');

define('SESSION_LIFETIME', 86400); // 1 hari
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error display (matikan di production)
ini_set('display_errors', 1);
error_reporting(E_ALL);
