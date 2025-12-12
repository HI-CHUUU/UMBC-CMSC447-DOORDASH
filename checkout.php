<?php
/**
 * Checkout Controller
 * * Handles order finalization, tips, and mock payments.
 * * Fixes: UTF-8 encoding.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: /UMBC447-DOORDASH/index.php?error=Please+login+as+customer");
    exit();
}

require 'config.php';

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['name'];

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $tip_amount = floatval($_POST['tip_amount'] ?? 0.00);
    
    // Validate Location
    if (!in_array($delivery_address, $campus_locations)) {
        header("Location: checkout.php?error=Invalid+campus+location");
        exit();
    }
    
    // Validate Tip
    if ($tip_amount < 0) $tip_amount = 0;

    try {
        $conn->begin_transaction();
        
        // Fetch Cart
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
        
        // Group Items
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
        
        $order_ids = [];
        $restaurant_count = count($orders_by_restaurant);
        $tip_per_order = $tip_amount / $restaurant_count;
        $eta = date('Y-m-d H:i:s', strtotime('+45 minutes'));

        // Create Orders
        foreach ($orders_by_restaurant as $restaurant_id => $order_data) {
            $stmt = $conn->prepare("
                INSERT INTO orders (customer_id, restaurant_id, total_amount, tip_amount, payment_status, status, delivery_address, notes, estimated_delivery_time)
                VALUES (?, ?, ?, ?, 'paid', 'pending', ?, ?, ?)
            ");
            
            $stmt->bind_param("iiddsss", $customer_id, $restaurant_id, $order_data['total'], $tip_per_order, $delivery_address, $notes, $eta);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $order_ids[] = $order_id;
            
            $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, menu_item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($order_data['items'] as $item) {
                $stmt_items->bind_param("iisid", $order_id, $item['menu_item_id'], $item['name'], $item['quantity'], $item['price']);
                $stmt_items->execute();
            }
        }
        
        // Clear Cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        
        $conn->commit();
        send_notification($conn, $customer_id, "Order(s) placed successfully! ETA: " . date('g:i A', strtotime($eta)));

        $order_ids_str = implode(',', $order_ids);
        header("Location: order-confirmation.php?orders=" . $order_ids_str);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: checkout.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Render View
$cart_items = [];
$subtotal = 0;
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
    }
} catch (Exception $e) { die("Database Error"); }

if (empty($cart_items)) { header("Location: view-cart.php?error=Cart+is+empty"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <title>Checkout – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
  <script>
      function updateTotal() {
          const subtotal = <?php echo $subtotal; ?>;
          const tipInput = document.getElementById('tip_amount');
          let tip = parseFloat(tipInput.value);
          if (isNaN(tip) || tip < 0) tip = 0;
          
          const total = subtotal + tip;
          document.getElementById('final-total-btn').innerText = "Place Order ($" + total.toFixed(2) + ")";
      }
  </script>
</head>
<body>
  <div class="dash">
    <div style="text-align: left; margin-bottom: 20px;">
        <a href="view-cart.php" class="back-link">&larr; Back to Cart</a>
    </div>

    <h2>🛒 Checkout</h2>

    <?php if (isset($_GET['error'])): ?>
        <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
    
    <div class="dash-content">
        <div class="checkout-section">
            <h3>Order Summary</h3>
            <?php 
            $current_restaurant = '';
            foreach ($cart_items as $item): 
                if ($current_restaurant !== $item['restaurant_name']):
                    if ($current_restaurant !== ''): echo '</ul></div>'; endif;
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
            
            <div class="checkout-total">Subtotal: $<?php echo number_format($subtotal, 2); ?></div>
        </div>

        <div class="checkout-section">
            <h3>Delivery & Payment</h3>
            <form method="POST" action="checkout.php" class="checkout-form">
                
                <div class="form-group">
                    <label for="delivery_address">Campus Delivery Location *</label>
                    <select id="delivery_address" name="delivery_address" required>
                        <option value="">-- Select a Location --</option>
                        <?php foreach ($campus_locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Special Instructions (Optional)</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Ex: Meet at front desk, call upon arrival"></textarea>
                </div>

                <div class="form-group">
                    <label for="tip_amount">Add Tip ($)</label>
                    <input type="number" id="tip_amount" name="tip_amount" step="0.50" min="0" value="0.00" oninput="updateTotal()">
                </div>

                <div class="form-group" style="background: #f1f3f5; padding: 15px; border-radius: 8px;">
                    <label>💳 Payment Details (Secure Mockup)</label>
                    <input type="text" placeholder="Card Number (XXXX-XXXX-XXXX-XXXX)" pattern="\d{4}-\d{4}-\d{4}-\d{4}" required style="margin-bottom: 10px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" placeholder="MM/YY" required style="width: 50%;">
                        <input type="text" placeholder="CVV" required style="width: 50%;">
                    </div>
                </div>

                <div class="checkout-actions">
                    <a href="view-cart.php" class="btn btn-secondary">Back to Cart</a>
                    <button type="submit" name="place_order" id="final-total-btn" class="btn btn-success btn-large">
                        Place Order ($<?php echo number_format($subtotal, 2); ?>)
                    </button>
                </div>
            </form>
        </div>
    </div>
  </div>
</body>
</html>