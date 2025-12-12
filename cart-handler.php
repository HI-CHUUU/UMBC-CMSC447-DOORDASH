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

$customer_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'add':
                // Add item to cart
                if (!isset($_POST['menu_item_id']) || !isset($_POST['restaurant_id'])) {
                    header("Location: /UMBC447-DOORDASH/dashboard.php?error=Missing+required+fields");
                    exit();
                }
                
                $menu_item_id = (int)$_POST['menu_item_id'];
                $restaurant_id = (int)$_POST['restaurant_id'];
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
                
                if ($quantity < 1) {
                    $quantity = 1;
                }
                
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
                
            case 'update':
                // Update cart item quantity
                if (!isset($_POST['cart_id']) || !isset($_POST['quantity'])) {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?error=Missing+required+fields");
                    exit();
                }
                
                $cart_id = (int)$_POST['cart_id'];
                $quantity = (int)$_POST['quantity'];
                
                if ($quantity < 1) {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?error=Invalid+quantity");
                    exit();
                }
                
                // Verify this cart item belongs to this customer and update
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND customer_id = ?");
                $stmt->bind_param("iii", $quantity, $cart_id, $customer_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?success=Cart+updated");
                } else {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?error=Could+not+update+cart");
                }
                exit();
                
            case 'remove':
                // Remove item from cart
                if (!isset($_POST['cart_id'])) {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?error=Missing+cart+ID");
                    exit();
                }
                
                $cart_id = (int)$_POST['cart_id'];
                
                // Delete cart item (only if it belongs to this customer)
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND customer_id = ?");
                $stmt->bind_param("ii", $cart_id, $customer_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?success=Item+removed");
                } else {
                    header("Location: /UMBC447-DOORDASH/view-cart.php?error=Could+not+remove+item");
                }
                exit();
                
            default:
                header("Location: /UMBC447-DOORDASH/view-cart.php?error=Invalid+action");
                exit();
        }
    } catch (Exception $e) {
        // Redirect to appropriate page with error
        $redirect = isset($_POST['restaurant_id']) ? 
            "/UMBC447-DOORDASH/menu.php?id=" . (int)$_POST['restaurant_id'] : 
            "/UMBC447-DOORDASH/view-cart.php";
        header("Location: " . $redirect . "?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// If no valid POST request, redirect to cart
header("Location: /UMBC447-DOORDASH/view-cart.php");
exit();
