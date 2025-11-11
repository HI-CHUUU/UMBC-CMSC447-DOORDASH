<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a restaurant owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    header("Location: /UMBC447-DOORDASH/dashboard.php?error=Unauthorized");
    exit();
}

require 'config.php';

$user_id = $_SESSION['user_id'];

// Get restaurant_id for this user
$restaurant_id = null;
try {
    $stmt = $conn->prepare("SELECT restaurant_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $restaurant_id = $row['restaurant_id'];
    }
} catch (Exception $e) {
    header("Location: restaurant-dashboard.php?error=" . urlencode($e->getMessage()));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];
    
    try {
        // Verify this order belongs to this restaurant
        $stmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND restaurant_id = ?");
        $stmt->bind_param("ii", $order_id, $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header("Location: restaurant-dashboard.php?error=Order+not+found");
            exit();
        }
        
        $order = $result->fetch_assoc();
        $current_status = $order['status'];
        $new_status = '';
        $success_message = '';
        
        // Handle different actions
        switch ($action) {
            case 'accept':
                if ($current_status === 'pending') {
                    $new_status = 'accepted';
                    $success_message = 'Order+accepted+successfully';
                } else {
                    header("Location: restaurant-dashboard.php?error=Order+already+processed");
                    exit();
                }
                break;
                
            case 'cancel':
                if ($current_status === 'pending') {
                    $new_status = 'cancelled';
                    $success_message = 'Order+declined';
                } else {
                    header("Location: restaurant-dashboard.php?error=Cannot+cancel+order");
                    exit();
                }
                break;
                
            case 'preparing':
                if ($current_status === 'accepted') {
                    $new_status = 'preparing';
                    $success_message = 'Order+marked+as+preparing';
                } else {
                    header("Location: restaurant-dashboard.php?error=Invalid+order+status");
                    exit();
                }
                break;
                
            case 'ready':
                if ($current_status === 'preparing') {
                    $new_status = 'ready';
                    $success_message = 'Order+marked+as+ready+for+pickup';
                } else {
                    header("Location: restaurant-dashboard.php?error=Invalid+order+status");
                    exit();
                }
                break;
                
            default:
                header("Location: restaurant-dashboard.php?error=Invalid+action");
                exit();
        }
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            header("Location: restaurant-dashboard.php?success=" . $success_message);
        } else {
            header("Location: restaurant-dashboard.php?error=Failed+to+update+order");
        }
        exit();
        
    } catch (Exception $e) {
        header("Location: restaurant-dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: restaurant-dashboard.php");
exit();
