<?php
/**
 * Admin Action Controller
 * * Handles administrative tasks like approving dashers and restaurant owners.
 */
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    // Generic Approval for both Dashers and Restaurants
    if ($action === 'approve_user') {
        $user_id = (int)$_POST['user_id'];
        
        // Approve the user
        $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Send notification
        send_notification($conn, $user_id, "Congratulations! Your account has been approved. You can now login and access the dashboard.");
        
        header("Location: dashboard.php?success=Account+Approved");
        exit();
    }
}
header("Location: dashboard.php");
?>