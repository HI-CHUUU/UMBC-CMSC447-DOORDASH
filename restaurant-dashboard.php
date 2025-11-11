<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a restaurant owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    header("Location: /UMBC447-DOORDASH/dashboard.php?error=Unauthorized");
    exit();
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Get restaurant_id for this user
$restaurant_id = null;
try {
    $stmt = $conn->prepare("SELECT restaurant_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $restaurant_id = $row['restaurant_id'];
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// If no restaurant assigned, show error
if ($restaurant_id === null) {
    die("Error: No restaurant assigned to this account. Please contact administrator.");
}

// Get restaurant info
$restaurant = null;
try {
    $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $restaurant = $result->fetch_assoc();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Fetch pending orders for this restaurant
$pending_orders = [];
$active_orders = [];
$completed_orders = [];

try {
    // Pending orders (need acceptance)
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        WHERE o.restaurant_id = ? AND o.status = 'pending'
        ORDER BY o.created_at ASC
    ");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Get order items
        $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $items_result = $stmt2->get_result();
        $row['items'] = [];
        while ($item = $items_result->fetch_assoc()) {
            $row['items'][] = $item;
        }
        $pending_orders[] = $row;
    }
    
    // Active orders (accepted, preparing, ready)
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, d.name as dasher_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        LEFT JOIN users d ON o.dasher_id = d.id
        WHERE o.restaurant_id = ? AND o.status IN ('accepted', 'preparing', 'ready')
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Get order items
        $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $items_result = $stmt2->get_result();
        $row['items'] = [];
        while ($item = $items_result->fetch_assoc()) {
            $row['items'][] = $item;
        }
        $active_orders[] = $row;
    }
    
    // Recently completed orders (last 10)
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, d.name as dasher_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        LEFT JOIN users d ON o.dasher_id = d.id
        WHERE o.restaurant_id = ? AND o.status IN ('picked_up', 'delivered', 'cancelled')
        ORDER BY o.updated_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $completed_orders[] = $row;
    }
} catch (Exception $e) {
    // Ignore errors
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Restaurant Dashboard – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>
  <div class="dash">
    <h2>🍽️ <?php echo htmlspecialchars($restaurant['name']); ?></h2>
    <p>Welcome, <?php echo htmlspecialchars($name); ?>!</p>
    <p>You are logged in as <strong>restaurant owner</strong></p>
    <p><a href="/UMBC447-DOORDASH/logout.php" class="logout-link">Logout</a></p>

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
        <!-- Pending Orders (Need Action) -->
        <div class="orders-section">
            <h3>⏳ Pending Orders (<?php echo count($pending_orders); ?>)</h3>
            <?php if (empty($pending_orders)): ?>
                <p style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    No pending orders at the moment.
                </p>
            <?php else: ?>
                <?php foreach ($pending_orders as $order): ?>
                    <div class="order-card urgent">
                        <div class="order-header">
                            <span class="order-id">Order #<?php echo $order['id']; ?></span>
                            <span class="order-status pending">⏳ Needs Your Response</span>
                        </div>
                        <div class="order-details">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p><strong>Ordered:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                            <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                            <?php if (!empty($order['notes'])): ?>
                                <p><strong>Special Instructions:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                            <?php endif; ?>
                            
                            <h5>Items:</h5>
                            <ul class="order-items-simple">
                                <?php foreach ($order['items'] as $item): ?>
                                    <li><?php echo htmlspecialchars($item['menu_item_name']); ?> × <?php echo $item['quantity']; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                        <div class="order-actions">
                            <form method="POST" action="restaurant-action.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="btn btn-sm btn-success">✓ Accept Order</button>
                            </form>
                            <form method="POST" action="restaurant-action.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to decline this order?');">✗ Decline Order</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Active Orders -->
        <div class="orders-section">
            <h3>🔥 Active Orders (<?php echo count($active_orders); ?>)</h3>
            <?php if (empty($active_orders)): ?>
                <p style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    No active orders.
                </p>
            <?php else: ?>
                <?php foreach ($active_orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-id">Order #<?php echo $order['id']; ?></span>
                            <span class="order-status <?php echo $order['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </div>
                        <div class="order-details">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <?php if ($order['dasher_name']): ?>
                                <p><strong>Dasher:</strong> <?php echo htmlspecialchars($order['dasher_name']); ?></p>
                            <?php else: ?>
                                <p><strong>Dasher:</strong> <em>Not assigned yet</em></p>
                            <?php endif; ?>
                            
                            <h5>Items:</h5>
                            <ul class="order-items-simple">
                                <?php foreach ($order['items'] as $item): ?>
                                    <li><?php echo htmlspecialchars($item['menu_item_name']); ?> × <?php echo $item['quantity']; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                        <div class="order-actions">
                            <?php if ($order['status'] === 'accepted'): ?>
                                <form method="POST" action="restaurant-action.php" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="action" value="preparing">
                                    <button type="submit" class="btn btn-sm btn-primary">Start Preparing</button>
                                </form>
                            <?php elseif ($order['status'] === 'preparing'): ?>
                                <form method="POST" action="restaurant-action.php" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="action" value="ready">
                                    <button type="submit" class="btn btn-sm btn-success">Mark as Ready</button>
                                </form>
                            <?php elseif ($order['status'] === 'ready'): ?>
                                <span class="badge-success">✓ Ready for Pickup - Waiting for Dasher</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recently Completed Orders -->
        <div class="orders-section">
            <h3>✓ Recent Completed Orders</h3>
            <?php if (empty($completed_orders)): ?>
                <p style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    No completed orders yet.
                </p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Dasher</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo $order['dasher_name'] ? htmlspecialchars($order['dasher_name']) : 'N/A'; ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                <td><?php echo date('M j, g:i A', strtotime($order['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
  </div>
</body>
</html>
