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
$cart_items = [];
$subtotal = 0;

// Fetch cart items
try {
    $stmt = $conn->prepare("
        SELECT c.id as cart_id, c.quantity, m.id as menu_item_id, m.name, m.price, m.description, r.name as restaurant_name, r.id as restaurant_id
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
    }
} catch (Exception $e) {
    die("Error fetching cart: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shopping Cart — UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>
  <div class="dash">
    <div style="text-align: left; margin-bottom: 20px;">
        <a href="dashboard.php" class="back-link">&larr; Back to Restaurants</a>
    </div>

    <h2>🛒 Shopping Cart</h2>
    <p>Review your items before checkout</p>

    <?php if (isset($_GET['success'])): ?>
        <div class="message success">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="message error">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="dash-content">
        <?php if (empty($cart_items)): ?>
            <p style="padding: 50px; background: #f8f9fa; border-radius: 8px; text-align: center; font-size: 18px;">
                Your cart is empty. Browse restaurants to add items!
            </p>
            <div style="margin-top: 30px;">
                <a href="dashboard.php" class="btn btn-primary">Browse Restaurants</a>
            </div>
        <?php else: ?>
            <div class="cart-section">
                <ul class="cart-items">
                    <?php 
                    $current_restaurant = '';
                    foreach ($cart_items as $item): 
                        if ($current_restaurant !== $item['restaurant_name']):
                            if ($current_restaurant !== ''):
                                echo '</ul></div>';
                            endif;
                            $current_restaurant = $item['restaurant_name'];
                    ?>
                            <div style="margin-top: 30px; margin-bottom: 15px;">
                                <h4 style="font-size: 20px; color: #5a7bc7; padding-bottom: 10px; border-bottom: 2px solid #5a7bc7;">
                                    <?php echo htmlspecialchars($current_restaurant); ?>
                                </h4>
                            </div>
                            <ul class="cart-items">
                    <?php endif; ?>
                    
                        <li class="cart-item">
                            <div class="cart-item-info">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?> each</div>
                            </div>
                            <div class="cart-item-actions">
                                <form method="POST" action="update-cart.php" style="display: inline;">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="quantity-input">
                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                </form>
                                <form method="POST" action="remove-from-cart.php" style="display: inline;">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                                <span style="font-weight: 600; color: #5a7bc7; margin-left: 10px;">
                                    $<?php echo number_format($item['item_total'], 2); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="cart-subtotal">
                    Subtotal: $<?php echo number_format($subtotal, 2); ?>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                    <a href="dashboard.php" class="btn btn-secondary" style="width: auto;">Continue Shopping</a>
                    <a href="checkout.php" class="btn btn-success" style="width: auto;">Proceed to Checkout</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
  </div>
</body>
</html>
