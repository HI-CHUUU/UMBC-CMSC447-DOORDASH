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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'])) {
    $customer_id = $_SESSION['user_id'];
    $cart_id = (int)$_POST['cart_id'];
    
    try {
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
    } catch (Exception $e) {
        header("Location: /UMBC447-DOORDASH/view-cart.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: /UMBC447-DOORDASH/view-cart.php");
exit();
