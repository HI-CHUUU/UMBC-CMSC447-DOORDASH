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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    $customer_id = $_SESSION['user_id'];
    $cart_id = (int)$_POST['cart_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity < 1) {
        header("Location: /UMBC447-DOORDASH/view-cart.php?error=Invalid+quantity");
        exit();
    }
    
    try {
        // Verify this cart item belongs to this customer
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND customer_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_id, $customer_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            header("Location: /UMBC447-DOORDASH/view-cart.php?success=Cart+updated");
        } else {
            header("Location: /UMBC447-DOORDASH/view-cart.php?error=Could+not+update+cart");
        }
        exit();
    } catch (Exception $e) {
        header("Location: /UMBC447-DOORDASH/view-cart.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: /UMBC447-DOORDASH/view-cart.php");
exit();
