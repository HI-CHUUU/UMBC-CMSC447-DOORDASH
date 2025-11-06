<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a dasher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dasher') {
    header("Location: /UMBC447-DOORDASH/dashboard.php?error=Unauthorized");
    exit();
}

require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $dasher_id = $_SESSION['user_id'];
    
    try {
        // Check if order is still available
        $stmt = $conn->prepare("SELECT id, status, dasher_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header("Location: /UMBC447-DOORDASH/dashboard.php?error=Order+not+found");
            exit();
        }
        
        $order = $result->fetch_assoc();
        
        if ($order['dasher_id'] !== null) {
            header("Location: /UMBC447-DOORDASH/dashboard.php?error=Order+already+accepted");
            exit();
        }
        
        // Accept the order
        $stmt = $conn->prepare("UPDATE orders SET dasher_id = ? WHERE id = ? AND dasher_id IS NULL");
        $stmt->bind_param("ii", $dasher_id, $order_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            header("Location: /UMBC447-DOORDASH/dashboard.php?success=Order+accepted");
        } else {
            header("Location: /UMBC447-DOORDASH/dashboard.php?error=Could+not+accept+order");
        }
        exit();
    } catch (Exception $e) {
        header("Location: /UMBC447-DOORDASH/dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: /UMBC447-DOORDASH/dashboard.php");
exit();
