<?php
/**
 * Restaurant Order Controller
 * * Handles state transitions initiated by Restaurant Owners.
 * - Accept Order: Confirms receipt.
 * - Start Preparing: Indicates kitchen activity.
 * - Mark Ready: Triggers Dasher dispatch.
 * - Notifications: Alerts the customer at each stage.
 */

session_start();
require 'config.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    header("Location: dashboard.php"); exit();
}

$user_id = $_SESSION['user_id'];

// Resolve Restaurant ID
$stmt = $conn->prepare("SELECT restaurant_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$restaurant_id = $res->fetch_assoc()['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];
    
    // Retrieve Customer ID to send notifications
    $stmt = $conn->prepare("SELECT customer_id FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $customer_id = $stmt->get_result()->fetch_assoc()['customer_id'];

    // State Machine
    if ($action === 'accept') {
        $conn->query("UPDATE orders SET status = 'accepted' WHERE id = $order_id");
        send_notification($conn, $customer_id, "Your order #$order_id has been accepted by the restaurant!");
        header("Location: restaurant-dashboard.php?success=Accepted");
    } elseif ($action === 'preparing') {
        $conn->query("UPDATE orders SET status = 'preparing' WHERE id = $order_id");
        header("Location: restaurant-dashboard.php?success=Preparing");
    } elseif ($action === 'ready') {
        $conn->query("UPDATE orders SET status = 'ready' WHERE id = $order_id");
        send_notification($conn, $customer_id, "Order #$order_id is ready! Finding a dasher...");
        header("Location: restaurant-dashboard.php?success=Ready");
    } elseif ($action === 'cancel') {
        $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = $order_id");
        send_notification($conn, $customer_id, "Order #$order_id was cancelled by the restaurant.");
        header("Location: restaurant-dashboard.php?success=Cancelled");
    }
}
?>