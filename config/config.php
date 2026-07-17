<?php
date_default_timezone_set('Africa/Accra');

define('APP_NAME', 'VoteHub');
define('APP_TAGLINE', 'Multi-Institution Online Voting Platform');
define('BASE_URL', '/votehub');

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'votehub');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session
define('SESSION_LIFETIME', 3600);

// OTP
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_LENGTH', 6);

// Uploads
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads');
define('MAX_FILE_SIZE', 2097152); // 2MB




