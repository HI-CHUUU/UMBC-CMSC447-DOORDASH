<?php
/**
 * Restaurant Order Controller
 * * Features: State management + Time tracking (Accepted/Ready).
 */

session_start();
require 'config.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    header("Location: dashboard.php"); exit();
}

$user_id = $_SESSION['user_id'];

// Get Restaurant ID
$stmt = $conn->prepare("SELECT restaurant_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$restaurant_id = $stmt->get_result()->fetch_assoc()['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];
    
    // Get customer ID for notifications
    $stmt = $conn->prepare("SELECT customer_id FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $customer_id = $stmt->get_result()->fetch_assoc()['customer_id'];

    if ($action === 'accept') {
        // Update Status + Time
        $conn->query("UPDATE orders SET status = 'accepted', accepted_at = NOW() WHERE id = $order_id");
        send_notification($conn, $customer_id, "Your order #$order_id has been accepted!");
        header("Location: restaurant-dashboard.php?success=Accepted");
    } elseif ($action === 'preparing') {
        $conn->query("UPDATE orders SET status = 'preparing' WHERE id = $order_id");
        header("Location: restaurant-dashboard.php?success=Preparing");
    } elseif ($action === 'ready') {
        // Update Status + Time
        $conn->query("UPDATE orders SET status = 'ready', ready_at = NOW() WHERE id = $order_id");
        send_notification($conn, $customer_id, "Order #$order_id is ready! Finding a dasher...");
        header("Location: restaurant-dashboard.php?success=Ready");
    } elseif ($action === 'cancel') {
        $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = $order_id");
        send_notification($conn, $customer_id, "Order #$order_id was cancelled by the restaurant.");
        header("Location: restaurant-dashboard.php?success=Cancelled");
    }
}
?>