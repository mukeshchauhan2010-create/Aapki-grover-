<?php
/**
 * EXAMPLE local override file. Copy this to config.local.php and fill in
 * your real values. config.local.php is git-ignored so secrets stay local.
 *
 *   cp config/config.local.example.php config/config.local.php
 */

// ── Base URL for this environment ──
define('BASE_URL', 'http://localhost/aapkigrocery/');   // or https://aapkigrocery.com/

// ── Database credentials (only if different from defaults) ──
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'aapki_grocery');
// define('DB_USER', 'root');
// define('DB_PASS', '');

// ── Google OAuth (from Google Cloud Console) ──
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// ── Facebook OAuth (from Meta for Developers) ──
// define('FACEBOOK_APP_ID',     'YOUR_FACEBOOK_APP_ID');
// define('FACEBOOK_APP_SECRET', 'YOUR_FACEBOOK_APP_SECRET');
