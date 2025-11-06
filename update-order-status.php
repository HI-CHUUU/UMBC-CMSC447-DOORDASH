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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    $dasher_id = $_SESSION['user_id'];
    
    // Validate status
    $valid_statuses = ['picked_up', 'delivered'];
    if (!in_array($new_status, $valid_statuses)) {
        header("Location: /UMBC447-DOORDASH/dashboard.php?error=Invalid+status");
        exit();
    }
    
    try {
        // Verify this dasher owns this order
        $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND dasher_id = ?");
        $stmt->bind_param("ii", $order_id, $dasher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header("Location: /UMBC447-DOORDASH/dashboard.php?error=Order+not+found");
            exit();
        }
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND dasher_id = ?");
        $stmt->bind_param("sii", $new_status, $order_id, $dasher_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = $new_status === 'delivered' ? 'Order+delivered' : 'Order+picked+up';
            header("Location: /UMBC447-DOORDASH/dashboard.php?success=" . $message);
        } else {
            header("Location: /UMBC447-DOORDASH/dashboard.php?error=Could+not+update+order");
        }
        exit();
    } catch (Exception $e) {
        header("Location: /UMBC447-DOORDASH/dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: /UMBC447-DOORDASH/dashboard.php");
exit();
