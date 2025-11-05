<?php
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
if (!in_array($role, ['customer','dasher','admin','restaurant'], true)) {
    $role = 'customer';
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $name, $email, $hash, $role);
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    header("Location: index.php?error=Email+already+registered");
    exit();
}

header("Location: index.php?success=Registration+successful!+Login+now.");
exit();
