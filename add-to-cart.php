<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: /UMBC447-DOORDASH/index.php?error=Please+login+as+customer");
    exit();
}

require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menu_item_id']) && isset($_POST['restaurant_id'])) {
    $customer_id = $_SESSION['user_id'];
    $menu_item_id = (int)$_POST['menu_item_id'];
    $restaurant_id = (int)$_POST['restaurant_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    try {
        // Check if item already in cart
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND menu_item_id = ?");
        $stmt->bind_param("ii", $customer_id, $menu_item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update quantity
            $row = $result->fetch_assoc();
            $new_quantity = $row['quantity'] + $quantity;
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_quantity, $row['id']);
            $stmt->execute();
        } else {
            // Add new item
            $stmt = $conn->prepare("INSERT INTO cart (customer_id, menu_item_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $customer_id, $menu_item_id, $quantity);
            $stmt->execute();
        }
        
        header("Location: /UMBC447-DOORDASH/menu.php?id=" . $restaurant_id . "&success=Added+to+cart");
        exit();
    } catch (Exception $e) {
        header("Location: /UMBC447-DOORDASH/menu.php?id=" . $restaurant_id . "&error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: /UMBC447-DOORDASH/dashboard.php");
exit();
