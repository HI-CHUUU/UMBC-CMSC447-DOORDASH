<?php
/**
 * User Registration Controller
 * * Updates: 
 * * - Dasher registration sets is_approved = 0.
 * * - Restaurant registration sets is_approved = 0 AND links restaurant_id.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';
$role  = $_POST['role'] ?? 'customer';
$restaurant_id = isset($_POST['restaurant_id']) && $_POST['restaurant_id'] !== '' ? (int)$_POST['restaurant_id'] : null;

// Basic Validation
if ($name === '' || $email === '' || $pass === '') {
    header("Location: index.php?error=All+fields+required");
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?error=Invalid+email");
    exit();
}
if (strlen($pass) < 6) {
    header("Location: index.php?error=Password+too+short");
    exit();
}

// Access Control
if ($role === 'admin') {
    header("Location: index.php?error=Admin+role+requires+internal+creation");
    exit();
}

// Logic: Dashers and Restaurants require approval. Customers are auto-approved.
$is_approved = 1; // Default true
if ($role === 'dasher' || $role === 'restaurant') {
    $is_approved = 0; // Pending
}

// Logic: Restaurant Owners MUST select a venue
if ($role === 'restaurant' && $restaurant_id === null) {
    header("Location: index.php?error=Please+select+a+restaurant");
    exit();
}

// Reset restaurant_id for non-restaurant roles (safety)
if ($role !== 'restaurant') {
    $restaurant_id = null;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, is_approved, restaurant_id) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssii", $name, $email, $hash, $role, $is_approved, $restaurant_id);
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    header("Location: index.php?error=Email+already+registered");
    exit();
}

if ($role === 'dasher' || $role === 'restaurant') {
    header("Location: index.php?success=Registration+successful!+Your+account+is+pending+admin+approval.");
} else {
    header("Location: index.php?success=Registration+successful!+Login+now.");
}
exit();
?>