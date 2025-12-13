<?php
/**
 * Restaurant Dashboard
 * * Management interface for restaurant owners.
 * * Features: Order Management, History, Menu Editing, Auto-Refresh.
 */

// CRITICAL: Force UTF-8 Encoding
header('Content-Type: text/html; charset=utf-8');

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
$stmt = $conn->prepare("SELECT is_approved, restaurant_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data['is_approved']) {
    exit("<html><head><link rel='stylesheet' href='style.css'></head><body><div class='dash' style='text-align:center;'><h2>⚠️ Account Pending Approval</h2><p>Your account is under review.</p><a href='logout.php' class='btn btn-secondary'>Logout</a></div></body></html>");
}

$restaurant_id = $user_data['restaurant_id'];
if ($restaurant_id === null) die("Error: No restaurant assigned.");

// --- DATA FETCHING ---
// 1. Restaurant Info
$restaurant = $conn->query("SELECT * FROM restaurants WHERE id = $restaurant_id")->fetch_assoc();

// 2. Pending Orders
$pending_orders = [];
$res = $conn->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.restaurant_id = $restaurant_id AND o.status = 'pending' ORDER BY o.created_at ASC");
while ($row = $res->fetch_assoc()) {
    $row['items'] = $conn->query("SELECT * FROM order_items WHERE order_id = " . $row['id'])->fetch_all(MYSQLI_ASSOC);
    $pending_orders[] = $row;
}

// 3. Active Orders
$active_orders = [];
$res = $conn->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.restaurant_id = $restaurant_id AND o.status IN ('accepted', 'preparing', 'ready') ORDER BY o.created_at DESC");
while ($row = $res->fetch_assoc()) {
    $row['items'] = $conn->query("SELECT * FROM order_items WHERE order_id = " . $row['id'])->fetch_all(MYSQLI_ASSOC);
    $active_orders[] = $row;
}

// 4. History (Completed)
$completed_orders = [];
$res = $conn->query("SELECT o.*, u.name as customer_name, d.name as dasher_name FROM orders o JOIN users u ON o.customer_id = u.id LEFT JOIN users d ON o.dasher_id = d.id WHERE o.restaurant_id = $restaurant_id AND o.status IN ('picked_up', 'delivered', 'cancelled') ORDER BY o.updated_at DESC LIMIT 20");
while ($row = $res->fetch_assoc()) $completed_orders[] = $row;

// 5. Menu Items
$my_menu_items = $conn->query("SELECT * FROM menu_items WHERE restaurant_id = $restaurant_id ORDER BY category, name")->fetch_all(MYSQLI_ASSOC);

// --- AJAX RESPONSE HANDLER ---
// Returns only the dynamic HTML for orders/history when requested via JS
if (isset($_GET['ajax_refresh'])) {
    // OUTPUT PENDING
    echo '<div class="orders-section"><h3>⏳ Pending Orders (' . count($pending_orders) . ')</h3>';
    if (empty($pending_orders)) echo '<p style="padding: 20px; background: #f8f9fa;">No pending orders.</p>';
    else {
        foreach ($pending_orders as $order) {
            echo '<div class="order-card urgent"><div class="order-header"><span class="order-id">Order #' . $order['id'] . '</span><span class="order-status pending">⏳ Needs Response</span></div><div class="order-details"><p><strong>Customer:</strong> ' . htmlspecialchars($order['customer_name']) . '</p><h5>Items:</h5><ul class="order-items-simple">';
            foreach ($order['items'] as $item) echo '<li>' . htmlspecialchars($item['menu_item_name']) . ' × ' . $item['quantity'] . '</li>';
            echo '</ul><p><strong>Total:</strong> $' . number_format($order['total_amount'], 2) . '</p></div><div class="order-actions"><form method="POST" action="restaurant-action.php" style="display:inline;"><input type="hidden" name="order_id" value="' . $order['id'] . '"><input type="hidden" name="action" value="accept"><button type="submit" class="btn btn-sm btn-success">✓ Accept</button></form> <form method="POST" action="restaurant-action.php" style="display:inline;"><input type="hidden" name="order_id" value="' . $order['id'] . '"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-danger">✗ Decline</button></form></div></div>';
        }
    }
    echo '</div>';

    // OUTPUT ACTIVE
    echo '<div class="orders-section"><h3>🔥 Active Orders (' . count($active_orders) . ')</h3>';
    if (empty($active_orders)) echo '<p style="padding: 20px; background: #f8f9fa;">No active orders.</p>';
    else {
        foreach ($active_orders as $order) {
            echo '<div class="order-card"><div class="order-header"><span class="order-id">Order #' . $order['id'] . '</span><span class="order-status ' . $order['status'] . '">' . ucfirst($order['status']) . '</span></div><div class="order-details"><p><strong>Customer:</strong> ' . htmlspecialchars($order['customer_name']) . '</p><ul class="order-items-simple">';
            foreach ($order['items'] as $item) echo '<li>' . htmlspecialchars($item['menu_item_name']) . ' × ' . $item['quantity'] . '</li>';
            echo '</ul></div><div class="order-actions">';
            if ($order['status'] === 'accepted') echo '<form method="POST" action="restaurant-action.php"><input type="hidden" name="order_id" value="' . $order['id'] . '"><input type="hidden" name="action" value="preparing"><button class="btn btn-sm btn-primary">Start Preparing</button></form>';
            elseif ($order['status'] === 'preparing') echo '<form method="POST" action="restaurant-action.php"><input type="hidden" name="order_id" value="' . $order['id'] . '"><input type="hidden" name="action" value="ready"><button class="btn btn-sm btn-success">Mark as Ready</button></form>';
            elseif ($order['status'] === 'ready') echo '<span class="badge-success">✓ Ready - Waiting for Dasher</span>';
            echo '</div></div>';
        }
    }
    echo '</div>';

    // OUTPUT HISTORY
    echo '<div class="orders-section" style="margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px;"><h3>📜 Order History</h3>';
    if (empty($completed_orders)) echo '<p>No completed orders.</p>';
    else {
        echo '<table class="admin-table"><thead><tr><th>ID</th><th>Customer</th><th>Dasher</th><th>Status</th><th>Completed At</th><th>Total</th></tr></thead><tbody>';
        foreach ($completed_orders as $o) {
            $comp_time = !empty($o['delivered_at']) ? $o['delivered_at'] : (!empty($o['picked_up_at']) ? $o['picked_up_at'] : $o['updated_at']);
            echo '<tr><td>#' . $o['id'] . '</td><td>' . htmlspecialchars($o['customer_name']) . '</td><td>' . ($o['dasher_name'] ?? '<em>None</em>') . '</td><td><span class="order-status ' . $o['status'] . '">' . ucfirst($o['status']) . '</span></td><td>' . date('M j, g:i A', strtotime($comp_time)) . '</td><td>$' . number_format($o['total_amount'], 2) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
    
    exit(); // Stop here so we only send the HTML fragment
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <title>Restaurant Dashboard – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
  <style>
      .menu-table input { padding: 5px; border: 1px solid #ccc; border-radius: 4px; width: 80px; }
      .menu-management { margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px; }
      html { scroll-behavior: smooth; } 
  </style>
  <script>
    // AJAX Polling: Updates orders every 5 seconds
    setInterval(function() {
        fetch(window.location.href.split('?')[0] + '?ajax_refresh=1')
            .then(res => res.text())
            .then(html => {
                if(html.length > 20) {
                    document.getElementById('live-orders').innerHTML = html;
                }
            });
    }, 5000); 
  </script>
</head>
<body>
  <div class="dash">
    <h2>🍽️ <?php echo htmlspecialchars($restaurant['name']); ?></h2>
    <p>Logged in as <strong>restaurant owner</strong> | <a href="/UMBC447-DOORDASH/logout.php" class="logout-link">Logout</a></p>

    <div class="dash-content">
        <div style="text-align:center; margin-bottom: 20px;">
            <a href="#orders" class="btn btn-sm btn-primary">Orders</a>
            <a href="#menu" class="btn btn-sm btn-secondary">Manage Menu</a>
        </div>

        <div id="live-orders">
            <div id="orders">
                <div class="orders-section"><h3>⏳ Pending Orders (<?php echo count($pending_orders); ?>)</h3>
                <?php if (empty($pending_orders)): ?><p style="padding: 20px; background: #f8f9fa;">No pending orders.</p><?php else: ?>
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="order-card urgent"><div class="order-header"><span class="order-id">Order #<?php echo $order['id']; ?></span><span class="order-status pending">⏳ Needs Response</span></div><div class="order-details"><p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p><h5>Items:</h5><ul class="order-items-simple"><?php foreach ($order['items'] as $item) echo '<li>' . htmlspecialchars($item['menu_item_name']) . ' × ' . $item['quantity'] . '</li>'; ?></ul><p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p></div><div class="order-actions"><form method="POST" action="restaurant-action.php" style="display:inline;"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="accept"><button type="submit" class="btn btn-sm btn-success">✓ Accept</button></form> <form method="POST" action="restaurant-action.php" style="display:inline;"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-danger">✗ Decline</button></form></div></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                
                <div class="orders-section"><h3>🔥 Active Orders (<?php echo count($active_orders); ?>)</h3>
                <?php if (empty($active_orders)): ?><p style="padding: 20px; background: #f8f9fa;">No active orders.</p><?php else: ?>
                    <?php foreach ($active_orders as $order): ?>
                        <div class="order-card"><div class="order-header"><span class="order-id">Order #<?php echo $order['id']; ?></span><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></div><div class="order-details"><p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p><ul class="order-items-simple"><?php foreach ($order['items'] as $item) echo '<li>' . htmlspecialchars($item['menu_item_name']) . ' × ' . $item['quantity'] . '</li>'; ?></ul></div><div class="order-actions">
                        <?php if ($order['status'] === 'accepted'): ?><form method="POST" action="restaurant-action.php"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="preparing"><button class="btn btn-sm btn-primary">Start Preparing</button></form>
                        <?php elseif ($order['status'] === 'preparing'): ?><form method="POST" action="restaurant-action.php"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="ready"><button class="btn btn-sm btn-success">Mark as Ready</button></form>
                        <?php elseif ($order['status'] === 'ready'): ?><span class="badge-success">✓ Ready - Waiting for Dasher</span><?php endif; ?>
                        </div></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>

            <div id="history" class="orders-section" style="margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px;">
                <h3>📜 Order History</h3>
                <?php if (empty($completed_orders)): ?><p>No completed orders.</p><?php else: ?>
                    <table class="admin-table"><thead><tr><th>ID</th><th>Customer</th><th>Dasher</th><th>Status</th><th>Completed At</th><th>Total</th></tr></thead><tbody>
                    <?php foreach ($completed_orders as $o): 
                        $comp_time = !empty($o['delivered_at']) ? $o['delivered_at'] : (!empty($o['picked_up_at']) ? $o['picked_up_at'] : $o['updated_at']);
                    ?>
                        <tr><td>#<?php echo $o['id']; ?></td><td><?php echo htmlspecialchars($o['customer_name']); ?></td><td><?php echo $o['dasher_name'] ?? '<em>None</em>'; ?></td><td><span class="order-status <?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span></td><td><?php echo date('M j, g:i A', strtotime($comp_time)); ?></td><td>$<?php echo number_format($o['total_amount'], 2); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div><div id="menu" class="menu-management">
            <h3>📝 Manage Menu</h3>
            
            <div class="add-item-form">
                <h4>Add New Item</h4>
                <form action="menu-action.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-row"><input type="text" name="name" placeholder="Item Name" required><input type="number" step="0.01" name="price" placeholder="Price ($)" required></div>
                    <div class="form-row"><input type="text" name="category" placeholder="Category" required><input type="text" name="description" placeholder="Description" required></div>
                    <button type="submit" class="btn btn-success">Add Item</button>
                </form>
            </div>

            <h4>Current Items</h4>
            <table class="admin-table menu-table">
                <thead><tr><th>Category</th><th>Name</th><th>Price</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($my_menu_items as $item): ?>
                    <tr><td><?php echo htmlspecialchars($item['category']); ?></td><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo htmlspecialchars($item['description']); ?></small></td>
                    <td><form action="menu-action.php" method="POST" style="margin:0; display:flex; gap:5px;"><input type="hidden" name="action" value="update_price"><input type="hidden" name="item_id" value="<?php echo $item['id']; ?>"><input type="number" step="0.01" name="price" value="<?php echo $item['price']; ?>"><button class="btn btn-xs btn-primary">Update</button></form></td>
                    <td><form action="menu-action.php" method="POST" style="margin:0;"><input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?php echo $item['id']; ?>"><button class="btn btn-xs btn-danger" onclick="return confirm('Delete?');">Remove</button></form></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>
</body>
</html>