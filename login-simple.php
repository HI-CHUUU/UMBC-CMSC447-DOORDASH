<?php
// Simple login without CSRF for testing
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=Invalid+email+address");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=Invalid+credentials");
        exit();
    }
}
header("Location: index.php");
exit();
