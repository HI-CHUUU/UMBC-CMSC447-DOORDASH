<?php
// session_boot.php
// Ensures reliable session handling in XAMPP environment

// Set custom session save path to local directory
$session_dir = __DIR__ . '/sessions';
if (!is_dir($session_dir)) {
    mkdir($session_dir, 0700);
}

// Configure session settings
ini_set('session.save_path', $session_dir);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 1440);

// Set secure cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/UMBC447-DOORDASH/',
    'domain' => '',
    'secure' => false,  // Set to true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start the session
session_start();
