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
$customer_name = $_SESSION['name'];

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($delivery_address)) {
        header("Location: checkout.php?error=Please+enter+delivery+address");
        exit();
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get cart items grouped by restaurant
        $stmt = $conn->prepare("
            SELECT c.id as cart_id, c.menu_item_id, c.quantity, 
                   m.name, m.price, m.restaurant_id, r.name as restaurant_name
            FROM cart c
            JOIN menu_items m ON c.menu_item_id = m.id
            JOIN restaurants r ON m.restaurant_id = r.id
            WHERE c.customer_id = ?
            ORDER BY m.restaurant_id
        ");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            header("Location: view-cart.php?error=Cart+is+empty");
            exit();
        }
        
        // Group items by restaurant
        $orders_by_restaurant = [];
        while ($row = $result->fetch_assoc()) {
            $restaurant_id = $row['restaurant_id'];
            if (!isset($orders_by_restaurant[$restaurant_id])) {
                $orders_by_restaurant[$restaurant_id] = [
                    'restaurant_name' => $row['restaurant_name'],
                    'items' => [],
                    'total' => 0
                ];
            }
            $orders_by_restaurant[$restaurant_id]['items'][] = $row;
            $orders_by_restaurant[$restaurant_id]['total'] += $row['price'] * $row['quantity'];
        }
        
        // Create separate order for each restaurant
        $order_ids = [];
        foreach ($orders_by_restaurant as $restaurant_id => $order_data) {
            // Insert order
            $stmt = $conn->prepare("
                INSERT INTO orders (customer_id, restaurant_id, total_amount, status, delivery_address, notes)
                VALUES (?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->bind_param("iidss", $customer_id, $restaurant_id, $order_data['total'], $delivery_address, $notes);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $order_ids[] = $order_id;
            
            // Insert order items
            $stmt = $conn->prepare("
                INSERT INTO order_items (order_id, menu_item_id, menu_item_name, quantity, price)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($order_data['items'] as $item) {
                $stmt->bind_param("iisid", $order_id, $item['menu_item_id'], $item['name'], $item['quantity'], $item['price']);
                $stmt->execute();
            }
        }
        
        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to order confirmation
        $order_ids_str = implode(',', $order_ids);
        header("Location: order-confirmation.php?orders=" . $order_ids_str);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: checkout.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Fetch cart items for display
$cart_items = [];
$subtotal = 0;
$restaurants = [];

try {
    $stmt = $conn->prepare("
        SELECT c.id as cart_id, c.quantity, m.name, m.price, m.description, 
               r.name as restaurant_name, r.id as restaurant_id
        FROM cart c
        JOIN menu_items m ON c.menu_item_id = m.id
        JOIN restaurants r ON m.restaurant_id = r.id
        WHERE c.customer_id = ?
        ORDER BY r.name, m.name
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['item_total'] = $row['price'] * $row['quantity'];
        $subtotal += $row['item_total'];
        $cart_items[] = $row;
        
        // Track unique restaurants
        if (!isset($restaurants[$row['restaurant_id']])) {
            $restaurants[$row['restaurant_id']] = $row['restaurant_name'];
        }
    }
} catch (Exception $e) {
    die("Error fetching cart: " . $e->getMessage());
}

// If cart is empty, redirect
if (empty($cart_items)) {
    header("Location: view-cart.php?error=Cart+is+empty");
    exit();
}

// Fetch customer's last used address (if any)
$last_address = '';
try {
    $stmt = $conn->prepare("SELECT delivery_address FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_address = $row['delivery_address'];
    }
} catch (Exception $e) {
    // Ignore error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Checkout – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>
  <div class="dash">
    <div style="text-align: left; margin-bottom: 20px;">
        <a href="view-cart.php" class="back-link">&larr; Back to Cart</a>
    </div>

    <h2>🛒 Checkout</h2>
    <p>Review your order and enter delivery details</p>

    <?php if (isset($_GET['error'])): ?>
        <div class="message error">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="dash-content">
        <!-- Order Summary -->
        <div class="checkout-section">
            <h3>Order Summary</h3>
            <?php 
            $current_restaurant = '';
            foreach ($cart_items as $item): 
                if ($current_restaurant !== $item['restaurant_name']):
                    if ($current_restaurant !== ''):
                        echo '</ul></div>';
                    endif;
                    $current_restaurant = $item['restaurant_name'];
            ?>
                    <div style="margin-top: 20px; margin-bottom: 15px;">
                        <h4 style="font-size: 20px; color: #5a7bc7; padding-bottom: 10px; border-bottom: 2px solid #5a7bc7;">
                            📍 <?php echo htmlspecialchars($current_restaurant); ?>
                        </h4>
                    </div>
                    <ul class="checkout-items">
            <?php endif; ?>
            
                <li class="checkout-item">
                    <div class="checkout-item-info">
                        <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                        <span class="item-quantity">Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?></span>
                    </div>
                    <span class="item-total">$<?php echo number_format($item['item_total'], 2); ?></span>
                </li>
            <?php endforeach; ?>
            </ul>
            </div>
            
            <div class="checkout-total">
                <strong>Total Amount: $<?php echo number_format($subtotal, 2); ?></strong>
            </div>
        </div>

        <!-- Delivery Information Form -->
        <div class="checkout-section">
            <h3>Delivery Information</h3>
            <form method="POST" action="checkout.php" class="checkout-form">
                <div class="form-group">
                    <label for="delivery_address">Delivery Address *</label>
                    <textarea 
                        id="delivery_address" 
                        name="delivery_address" 
                        rows="3" 
                        placeholder="Enter your complete delivery address"
                        required
                    ><?php echo htmlspecialchars($last_address); ?></textarea>
                    <small>Please provide a complete address including street, city, and zip code</small>
                </div>

                <div class="form-group">
                    <label for="notes">Special Instructions (Optional)</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        rows="2" 
                        placeholder="Any special instructions for the restaurant or dasher?"
                    ></textarea>
                </div>

                <div class="order-info-box">
                    <h4>📋 Order Information</h4>
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
                    <p><strong>Restaurant(s):</strong> <?php echo implode(', ', $restaurants); ?></p>
                    <p><strong>Total Items:</strong> <?php echo array_sum(array_column($cart_items, 'quantity')); ?></p>
                    <p><strong>Total Amount:</strong> $<?php echo number_format($subtotal, 2); ?></p>
                    <?php if (count($restaurants) > 1): ?>
                        <p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 6px; margin-top: 10px;">
                            ℹ️ <strong>Note:</strong> Your order contains items from <?php echo count($restaurants); ?> different restaurants. 
                            Separate orders will be created for each restaurant.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="checkout-actions">
                    <a href="view-cart.php" class="btn btn-secondary">Back to Cart</a>
                    <button type="submit" name="place_order" class="btn btn-success btn-large">
                        Place Order ($<?php echo number_format($subtotal, 2); ?>)
                    </button>
                </div>
            </form>
        </div>
    </div>
  </div>
</body>
</html>
