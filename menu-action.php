<?php
/**
 * Menu Management Controller
 * * Handles Adding, Removing, and Updating menu items.
 * * Security: Strict checks to ensure owners only modify their own data.
 */

session_start();
require 'config.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get Restaurant ID
$stmt = $conn->prepare("SELECT restaurant_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$restaurant_id = $stmt->get_result()->fetch_assoc()['restaurant_id'];

if (!$restaurant_id) {
    die("Error: No restaurant associated with this account.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // --- ADD ITEM ---
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $category = trim($_POST['category']);
        // Default image for now
        $image = 'placeholder.jpg'; 

        $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdss", $restaurant_id, $name, $desc, $price, $category, $image);
        $stmt->execute();
        
        header("Location: restaurant-dashboard.php?success=Item+Added");
        exit();
    }

    // --- UPDATE PRICE ---
    if ($action === 'update_price') {
        $item_id = (int)$_POST['item_id'];
        $new_price = (float)$_POST['price'];

        // Security: Ensure item belongs to this restaurant
        $stmt = $conn->prepare("UPDATE menu_items SET price = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->bind_param("dii", $new_price, $item_id, $restaurant_id);
        $stmt->execute();

        header("Location: restaurant-dashboard.php?success=Price+Updated");
        exit();
    }

    // --- REMOVE ITEM ---
    if ($action === 'delete') {
        $item_id = (int)$_POST['item_id'];

        // Security: Ensure item belongs to this restaurant
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ? AND restaurant_id = ?");
        $stmt->bind_param("ii", $item_id, $restaurant_id);
        $stmt->execute();

        header("Location: restaurant-dashboard.php?success=Item+Removed");
        exit();
    }
}
header("Location: restaurant-dashboard.php");
?>