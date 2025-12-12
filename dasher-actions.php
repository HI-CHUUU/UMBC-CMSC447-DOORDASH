<?php
/**
 * Dasher Action Controller
 * * Handles delivery logistics.
 * - Availability Toggle: Marks dasher as online/offline.
 * - Accept Order: Assigns an open order to the current dasher.
 * - Update Status: Progresses order through 'picked_up' and 'delivered' states.
 */

session_start();
require 'config.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dasher') {
    header("Location: dashboard.php"); exit();
}

$dasher_id = $_SESSION['user_id'];

// SECURITY: Verify Approval
$stmt = $conn->prepare("SELECT is_approved FROM users WHERE id = ?");
$stmt->bind_param("i", $dasher_id);
$stmt->execute();
$is_approved = (bool)$stmt->get_result()->fetch_assoc()['is_approved'];

if (!$is_approved) {
    header("Location: dashboard.php?error=Account+not+approved");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    // Action: Toggle Online/Offline
    if ($action === 'toggle_availability') {
        $stmt = $conn->prepare("SELECT is_available FROM dasher_availability WHERE dasher_id = ?");
        $stmt->bind_param("i", $dasher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $conn->query("UPDATE dasher_availability SET is_available = NOT is_available WHERE dasher_id = $dasher_id");
        } else {
            $conn->query("INSERT INTO dasher_availability (dasher_id, is_available) VALUES ($dasher_id, TRUE)");
        }
        header("Location: dashboard.php");
    }
    
    // Action: Accept Gig
    if ($action === 'accept_order') {
        $order_id = $_POST['order_id'];
        $stmt = $conn->prepare("UPDATE orders SET dasher_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $dasher_id, $order_id);
        $stmt->execute();
        
        // Notify Customer
        $stmt = $conn->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $cid = $stmt->get_result()->fetch_assoc()['customer_id'];
        send_notification($conn, $cid, "A dasher has accepted your order and is on the way!");
        
        header("Location: dashboard.php?success=Order+Accepted");
    }
    
    // Action: Update Delivery Status
    if ($action === 'update_status') {
        $order_id = $_POST['order_id'];
        $status = $_POST['new_status']; // 'picked_up' or 'delivered'
        
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND dasher_id = ?");
        $stmt->bind_param("sii", $status, $order_id, $dasher_id);
        $stmt->execute();
        
        // Notify Customer
        $stmt = $conn->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $cid = $stmt->get_result()->fetch_assoc()['customer_id'];
        
        if ($status === 'picked_up') {
            send_notification($conn, $cid, "Your order has been picked up!");
        } elseif ($status === 'delivered') {
            send_notification($conn, $cid, "Your order has been delivered. Enjoy!");
        }
        
        header("Location: dashboard.php");
    }
}
?>