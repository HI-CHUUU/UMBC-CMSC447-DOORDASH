<?php
/**
 * Admin Action: Create Dasher
 * * Logic handler for onboarding new delivery workers.
 * This file is restricted to Administrators only.
 */
session_start();
require 'config.php';

// Strict Role Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $role = 'dasher';

    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hash, $role);
        $stmt->execute();
        header("Location: dashboard.php?success=Dasher+Created");
    } catch (Exception $e) {
        header("Location: dashboard.php?error=Email+already+exists");
    }
}
?>