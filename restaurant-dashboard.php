<?php
/**
 * Restaurant Dashboard
 * * Management interface for restaurant owners.
 * * Update: Checks for Admin Approval.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    header("Location: /UMBC447-DOORDASH/dashboard.php?error=Unauthorized");
    exit();
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// --- APPROVAL CHECK ---
// We must check this every time because session data might be stale
$stmt = $conn->prepare("SELECT is_approved, restaurant_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data['is_approved']) {
    // Show Pending Screen instead of dashboard
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><title>Pending Approval</title>
        <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
    </head>
    <body>
        <div class="dash" style="text-align:center;">
            <h2>⚠️ Account Pending Approval</h2>
            <p>Welcome, <?php echo htmlspecialchars($name); ?>!</p>
            <div class="message error" style="background:#fff3cd; color:#856404; border:1px solid #ffeeba;">
                <p>Your Restaurant Owner account is currently under review by an administrator.</p>
                <p>You cannot manage your restaurant until your account is approved.</p>
            </div>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$restaurant_id = $user_data['restaurant_id'];

if ($restaurant_id === null) die("Error: No restaurant assigned to this account.");

$restaurant = null;
try {
    $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $restaurant = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) { die("Database Error"); }

$pending_orders = [];
$active_orders = [];
$completed_orders = [];

// Fetch Pending
try {
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
        $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $row['items'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $pending_orders[] = $row;
    }
} catch (Exception $e) {}

// Fetch Active
try {
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
        $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $row['items'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $active_orders[] = $row;
    }
} catch (Exception $e) {}

// Fetch Completed
try {
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, d.name as dasher_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        LEFT JOIN users d ON o.dasher_id = d.id
        WHERE o.restaurant_id = ? AND o.status IN ('picked_up', 'delivered', 'cancelled')
        ORDER BY o.updated_at DESC LIMIT 10
    ");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $completed_orders[] = $row;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <title>Restaurant Dashboard – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>
  <div class="dash">
    <h2>🍽️ <?php echo htmlspecialchars($restaurant['name']); ?></h2>
    <p>Welcome, <?php echo htmlspecialchars($name); ?>!</p>
    <p>Logged in as <strong>restaurant owner</strong></p>
    <p><a href="/UMBC447-DOORDASH/logout.php" class="logout-link">Logout</a></p>

    <?php if (isset($_GET['success'])): ?>
        <div class="message success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <div class="dash-content">
        <div class="orders-section">
            <h3>⏳ Pending Orders (<?php echo count($pending_orders); ?>)</h3>
            <?php if (empty($pending_orders)): ?>
                <p style="padding: 20px; background: #f8f9fa; border-radius: 8px;">No pending orders.</p>
            <?php else: ?>
                <?php foreach ($pending_orders as $order): ?>
                    <div class="order-card urgent">
                        <div class="order-header">
                            <span class="order-id">Order #<?php echo $order['id']; ?></span>
                            <span class="order-status pending">⏳ Needs Response</span>
                        </div>
                        <div class="order-details">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
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
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Decline this order?');">✗ Decline Order</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="orders-section">
            <h3>🔥 Active Orders (<?php echo count($active_orders); ?>)</h3>
            <?php if (empty($active_orders)): ?>
                <p style="padding: 20px; background: #f8f9fa; border-radius: 8px;">No active orders.</p>
            <?php else: ?>
                <?php foreach ($active_orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-id">Order #<?php echo $order['id']; ?></span>
                            <span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                        </div>
                        <div class="order-details">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <ul class="order-items-simple">
                                <?php foreach ($order['items'] as $item): ?>
                                    <li><?php echo htmlspecialchars($item['menu_item_name']); ?> × <?php echo $item['quantity']; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="order-actions">
                            <?php if ($order['status'] === 'accepted'): ?>
                                <form method="POST" action="restaurant-action.php">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="action" value="preparing">
                                    <button class="btn btn-sm btn-primary">Start Preparing</button>
                                </form>
                            <?php elseif ($order['status'] === 'preparing'): ?>
                                <form method="POST" action="restaurant-action.php">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="action" value="ready">
                                    <button class="btn btn-sm btn-success">Mark as Ready</button>
                                </form>
                            <?php elseif ($order['status'] === 'ready'): ?>
                                <span class="badge-success">✓ Ready - Waiting for Dasher</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
  </div>
</body>
</html>