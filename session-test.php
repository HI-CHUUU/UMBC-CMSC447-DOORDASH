<?php
// session-test.php
// Test file to verify session persistence

require 'session_boot.php';

// Initialize counter if not set
if (!isset($_SESSION['counter'])) {
    $_SESSION['counter'] = 0;
}

// Increment counter
$_SESSION['counter']++;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Test — UMBC447-DOORDASH</title>
    <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>
    <div class="test-box">
        <h1>Session Test</h1>
        <p>Refresh this page to see the counter increase.</p>
        
        <div class="counter"><?php echo $_SESSION['counter']; ?></div>
        
        <div class="status success">
            ✓ Session is working! Counter increases on each refresh.
        </div>
        
        <p><small>Session ID: <?php echo session_id(); ?></small></p>
        <p style="margin-top: 20px;"><a href="index.php" class="back-link">← Back to Login</a></p>
    </div>
</body>
</html>
