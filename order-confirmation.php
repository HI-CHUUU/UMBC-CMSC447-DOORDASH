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

// Get order IDs from query string
if (!isset($_GET['orders']) || empty($_GET['orders'])) {
    header("Location: dashboard.php");
    exit();
}

$order_ids_str = $_GET['orders'];
$order_ids = array_map('intval', explode(',', $order_ids_str));

// Fetch order details
$orders = [];
try {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $conn->prepare("
        SELECT o.*, r.name as restaurant_name, r.image_url as restaurant_image
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.id
        WHERE o.id IN ($placeholders) AND o.customer_id = ?
        ORDER BY o.id
    ");
    
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$customer_id]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
        
        // Fetch order items
        $stmt2 = $conn->prepare("
            SELECT menu_item_name, quantity, price
            FROM order_items
            WHERE order_id = ?
        ");
        $stmt2->bind_param("i", $order_id);
        $stmt2->execute();
        $items_result = $stmt2->get_result();
        
        $row['items'] = [];
        while ($item = $items_result->fetch_assoc()) {
            $row['items'][] = $item;
        }
        
        $orders[] = $row;
    }
} catch (Exception $e) {
    die("Error fetching orders: " . $e->getMessage());
}

if (empty($orders)) {
    header("Location: dashboard.php?error=Orders+not+found");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Confirmation – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
  <style>
    .success-animation {
        text-align: center;
        padding: 40px 20px;
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-radius: 10px;
        margin-bottom: 30px;
    }
    .success-checkmark {
        font-size: 80px;
        color: #28a745;
        animation: scaleIn 0.5s ease-in-out;
    }
    @keyframes scaleIn {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    .order-number {
        font-size: 24px;
        color: #155724;
        font-weight: 600;
        margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="dash">
    <div class="success-animation">
        <div class="success-checkmark">✓</div>
        <h2 style="color: #155724; margin: 15px 0;">Order Placed Successfully!</h2>
        <p style="color: #155724; font-size: 18px;">
            Thank you for your order. The restaurant will review your request shortly.
        </p>
    </div>

    <div class="dash-content">
        <h3>Order Details</h3>
        
        <?php foreach ($orders as $order): ?>
            <div class="order-confirmation-card">
                <div class="order-header">
                    <div>
                        <span class="order-id">Order #<?php echo $order['id']; ?></span>
                        <span class="order-status pending">⏳ Awaiting Restaurant Acceptance</span>
                    </div>
                    <div class="order-restaurant">
                        <strong>📍 <?php echo htmlspecialchars($order['restaurant_name']); ?></strong>
                    </div>
                </div>

                <div class="order-details">
                    <h4>Items Ordered:</h4>
                    <ul class="order-items-list">
                        <?php foreach ($order['items'] as $item): ?>
                            <li>
                                <span><?php echo htmlspecialchars($item['menu_item_name']); ?> × <?php echo $item['quantity']; ?></span>
                                <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="order-info">
                        <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                        <?php if (!empty($order['notes'])): ?>
                            <p><strong>Special Instructions:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                        <?php endif; ?>
                        <p><strong>Order Time:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>

                    <div class="order-total">
                        <strong>Total: $<?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                </div>

                <!-- Order Status Tracker -->
                <div class="order-tracker">
                    <h4>Order Progress:</h4>
                    <div class="tracker-steps">
                        <div class="tracker-step active">
                            <div class="step-icon">1</div>
                            <div class="step-label">Order Placed</div>
                        </div>
                        <div class="tracker-step">
                            <div class="step-icon">2</div>
                            <div class="step-label">Restaurant Accepts</div>
                        </div>
                        <div class="tracker-step">
                            <div class="step-icon">3</div>
                            <div class="step-label">Preparing</div>
                        </div>
                        <div class="tracker-step">
                            <div class="step-icon">4</div>
                            <div class="step-label">Ready for Pickup</div>
                        </div>
                        <div class="tracker-step">
                            <div class="step-icon">5</div>
                            <div class="step-label">Out for Delivery</div>
                        </div>
                        <div class="tracker-step">
                            <div class="step-icon">6</div>
                            <div class="step-label">Delivered</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="order-next-steps">
            <h4>What happens next?</h4>
            <ol style="text-align: left; padding-left: 20px; line-height: 1.8;">
                <li>The restaurant will review and accept your order</li>
                <li>Once accepted, the restaurant will start preparing your food</li>
                <li>When ready, a dasher will be assigned to deliver your order</li>
                <li>You can track your order status from your dashboard</li>
            </ol>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="dashboard.php" class="btn btn-primary">View My Orders</a>
            <a href="dashboard.php" class="btn btn-secondary">Order More Food</a>
        </div>
    </div>
  </div>
</body>
</html>
