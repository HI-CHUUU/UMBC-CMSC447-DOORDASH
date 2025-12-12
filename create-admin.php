<?php
// Create admin user with correct password hash
require 'config.php';

// Generate fresh password hash for Admin123
$password = 'Admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Creating admin user...\n\n";
echo "Password: $password\n";
echo "New Hash: $hash\n\n";

// Delete existing admin if exists
try {
    $conn->query("DELETE FROM users WHERE email = 'admin@umbc447.com'");
    echo "Deleted old admin user (if existed)\n";
} catch (Exception $e) {
    echo "No old admin to delete\n";
}

// Insert new admin with fresh hash
try {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $name = 'Admin Tester';
    $email = 'admin@umbc447.com';
    $role = 'admin';
    $stmt->bind_param("ssss", $name, $email, $hash, $role);
    $stmt->execute();
    
    echo "\nâœ“ Admin user created successfully!\n";
    echo "âœ“ Email: admin@umbc447.com\n";
    echo "âœ“ Password: Admin123\n";
    echo "âœ“ Role: admin\n\n";
    
    // Verify it works
    if (password_verify($password, $hash)) {
        echo "âœ“ Password verification: SUCCESS!\n";
        echo "âœ“ You can now login with Admin123\n";
    } else {
        echo "âœ— Password verification: FAILED\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
