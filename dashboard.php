<?php
// CRITICAL: Force UTF-8 Encoding to prevent weird characters
header('Content-Type: text/html; charset=utf-8');

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth Guard: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /UMBC447-DOORDASH/index.php?error=Please+login");
    exit();
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];

// Re-fetch approval status from DB to ensure it's current
$stmt = $conn->prepare("SELECT is_approved FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_user = $stmt->get_result()->fetch_assoc();
$is_approved = $res_user ? (bool)$res_user['is_approved'] : false;
$_SESSION['is_approved'] = $is_approved;

// Redirect Restaurant Owners immediately
if ($role === 'restaurant') {
    header("Location: /UMBC447-DOORDASH/restaurant-dashboard.php");
    exit();
}

// --- NOTIFICATIONS LOGIC ---
// Fetch unread notifications
$notifications = [];
try {
    $stmt = $conn->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    // Mark as read immediately
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
} catch (Exception $e) {}

// --- DATA FETCHING (Common for both initial load and AJAX) ---

// 1. ADMIN DATA
$pending_dashers = [];
$pending_restaurants = [];
$all_orders = [];
if ($role === 'admin') {
    $pending_dashers = $conn->query("SELECT id, name, email, created_at FROM users WHERE role = 'dasher' AND is_approved = 0")->fetch_all(MYSQLI_ASSOC);
    $pending_restaurants = $conn->query("SELECT u.id, u.name, u.email, u.created_at, r.name as venue_name FROM users u LEFT JOIN restaurants r ON u.restaurant_id = r.id WHERE u.role = 'restaurant' AND u.is_approved = 0")->fetch_all(MYSQLI_ASSOC);
    // Fetch all orders with timestamps
    $all_orders = $conn->query("
        SELECT o.*, r.name as restaurant_name, u.name as customer_name, d.name as dasher_name 
        FROM orders o 
        JOIN restaurants r ON o.restaurant_id = r.id 
        JOIN users u ON o.customer_id = u.id 
        LEFT JOIN users d ON o.dasher_id = d.id 
        ORDER BY o.created_at DESC LIMIT 50
    ")->fetch_all(MYSQLI_ASSOC);
}

// 2. CUSTOMER DATA
$restaurants = [];
$my_orders = [];
$cart_count = 0;
if ($role === 'customer') {
    $res = $conn->query("SELECT id, name, description, image_url FROM restaurants");
    while ($row = $res->fetch_assoc()) $restaurants[] = $row;
    
    // Fetch My Orders with Timestamps
    $stmt = $conn->prepare("
        SELECT o.*, r.name as restaurant_name 
        FROM orders o 
        JOIN restaurants r ON o.restaurant_id = r.id 
        WHERE o.customer_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $my_orders[] = $row;
    
    // Cart Count
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count = (int)$stmt->get_result()->fetch_assoc()['total'];
}

// 3. DASHER DATA
$dasher_availability = false;
$available_orders = [];
if ($role === 'dasher' && $is_approved) {
    // Check Availability
    $stmt = $conn->prepare("SELECT is_available FROM dasher_availability WHERE dasher_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $dasher_availability = (bool)$res->fetch_assoc()['is_available'];
    } else {
        $conn->query("INSERT INTO dasher_availability (dasher_id, is_available) VALUES ($user_id, FALSE)");
        $dasher_availability = false;
    }
    
    // Fetch Available Orders
    $stmt = $conn->prepare("
        SELECT o.*, r.name as restaurant_name, u.name as customer_name 
        FROM orders o 
        JOIN restaurants r ON o.restaurant_id = r.id 
        JOIN users u ON o.customer_id = u.id 
        WHERE (o.status = 'pending' OR o.status = 'ready') AND o.dasher_id IS NULL 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $available_orders[] = $row;
    
    // Fetch My Deliveries
    $stmt = $conn->prepare("
        SELECT o.*, r.name as restaurant_name, u.name as customer_name 
        FROM orders o 
        JOIN restaurants r ON o.restaurant_id = r.id 
        JOIN users u ON o.customer_id = u.id 
        WHERE o.dasher_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $my_orders[] = $row;
}

// --- AJAX HANDLER ---
// If 'ajax_refresh' is in the URL, return ONLY the dynamic content div
if (isset($_GET['ajax_refresh'])) {
    
    // ADMIN AJAX OUTPUT
    if ($role === 'admin') {
        if (!empty($pending_dashers)) {
            echo '<div style="background: #fff3cd; padding: 10px; border-radius: 8px; margin-bottom: 20px;"><h4>⚠️ Pending Dashers</h4><table class="admin-table">';
            foreach ($pending_dashers as $dasher) {
                echo '<tr><td>' . htmlspecialchars($dasher['email']) . '</td><td><form method="POST" action="admin-actions.php" style="margin:0;"><input type="hidden" name="action" value="approve_user"><input type="hidden" name="user_id" value="' . $dasher['id'] . '"><button class="btn btn-xs btn-success">Approve</button></form></td></tr>';
            }
            echo '</table></div>';
        }
        if (!empty($pending_restaurants)) {
            echo '<div style="background: #d4edda; padding: 10px; border-radius: 8px; margin-bottom: 20px;"><h4>🏪 Pending Restaurants</h4><table class="admin-table">';
            foreach ($pending_restaurants as $owner) {
                echo '<tr><td>' . htmlspecialchars($owner['email']) . ' (' . htmlspecialchars($owner['venue_name'] ?? 'Unassigned') . ')</td><td><form method="POST" action="admin-actions.php" style="margin:0;"><input type="hidden" name="action" value="approve_user"><input type="hidden" name="user_id" value="' . $owner['id'] . '"><button class="btn btn-xs btn-success">Approve</button></form></td></tr>';
            }
            echo '</table></div>';
        }
        echo '<h4>All Orders (Detailed Tracking)</h4><table class="admin-table"><thead><tr><th>ID</th><th>Restaurant</th><th>Customer</th><th>Dasher</th><th>Status</th><th>Ordered At</th><th>Delivered At</th></tr></thead><tbody>';
        foreach ($all_orders as $o) {
            $del_time = !empty($o['delivered_at']) ? date('g:i A', strtotime($o['delivered_at'])) : '<em style="color:#999;">In Progress</em>';
            echo '<tr><td>#' . $o['id'] . '</td><td>' . htmlspecialchars($o['restaurant_name']) . '</td><td>' . htmlspecialchars($o['customer_name']) . '</td><td>' . ($o['dasher_name'] ?? '-') . '</td><td><span class="order-status ' . $o['status'] . '">' . ucfirst(str_replace('_', ' ', $o['status'])) . '</span></td><td>' . date('g:i A', strtotime($o['created_at'])) . '</td><td>' . $del_time . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    
    // CUSTOMER AJAX OUTPUT
    elseif ($role === 'customer') {
        if (!empty($my_orders)) {
            echo '<div class="orders-section"><h4>📦 My Orders</h4>';
            foreach ($my_orders as $order) {
                $ordered_at = date('M j, g:i A', strtotime($order['created_at']));
                $delivered_at = !empty($order['delivered_at']) ? '<br><strong style="color: #28a745;">Delivered:</strong> ' . date('M j, g:i A', strtotime($order['delivered_at'])) : '';
                
                echo '<div class="order-card">
                        <div class="order-header"><span class="order-id">#' . $order['id'] . '</span><span class="order-status ' . $order['status'] . '">' . ucfirst($order['status']) . '</span></div>
                        <div class="order-details">
                            <p><strong>Restaurant:</strong> ' . htmlspecialchars($order['restaurant_name']) . '</p>
                            <p><strong>Total:</strong> $' . number_format($order['total_amount'] + $order['tip_amount'], 2) . '</p>
                            <p style="font-size: 0.9em; color: #555; margin-top: 5px;"><strong>Ordered:</strong> ' . $ordered_at . $delivered_at . '</p>
                        </div>
                        <div class="order-actions"><a href="order-confirmation.php?orders=' . $order['id'] . '" class="btn btn-sm btn-info">📍 Track Live Progress</a></div>
                      </div>';
            }
            echo '</div>';
        }
        // Restaurant list is static but included for consistency
        echo '<h4 style="text-align: left; margin-top: 30px;">Browse Restaurants</h4><ul class="restaurant-list">';
        foreach ($restaurants as $resto) {
            echo '<li><a href="menu.php?id=' . $resto['id'] . '" class="restaurant-item-link"><img src="' . htmlspecialchars($resto['image_url'] ?? 'placeholder.jpg') . '" alt="Img"><div><h4>' . htmlspecialchars($resto['name']) . '</h4><p>' . htmlspecialchars($resto['description']) . '</p></div></a></li>';
        }
        echo '</ul>';
    }
    
    // DASHER AJAX OUTPUT
    elseif ($role === 'dasher' && $is_approved) {
        echo '<h3>Dasher Dashboard</h3>
              <div class="availability-toggle">Status: ' . ($dasher_availability ? '🟢 Available' : '🔴 Unavailable') . ' 
              <form method="POST" action="dasher-actions.php" style="margin-top: 10px;">
                <input type="hidden" name="action" value="toggle_availability">
                <button type="submit" class="btn ' . ($dasher_availability ? 'btn-danger' : 'btn-success') . '">' . ($dasher_availability ? 'Go Offline' : 'Go Online') . '</button>
              </form></div>';
        
        if ($dasher_availability && !empty($available_orders)) {
            echo '<div class="orders-section"><h4>Available Deliveries</h4>';
            foreach ($available_orders as $order) {
                echo '<div class="order-card"><p><strong>Pay:</strong> $' . number_format($order['total_amount'] + $order['tip_amount'], 2) . '</p><p><strong>To:</strong> ' . htmlspecialchars($order['delivery_address']) . '</p><form method="POST" action="dasher-actions.php"><input type="hidden" name="action" value="accept_order"><input type="hidden" name="order_id" value="' . $order['id'] . '"><button class="btn btn-sm btn-success">Accept</button></form></div>';
            }
            echo '</div>';
        }
        
        if (!empty($my_orders)) {
            echo '<div class="orders-section"><h4>My Deliveries (Earnings)</h4>';
            foreach ($my_orders as $order) {
                $action_btn = '';
                if ($order['status'] === 'ready') {
                    $action_btn = '<form method="POST" action="dasher-actions.php"><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="' . $order['id'] . '"><input type="hidden" name="new_status" value="picked_up"><button class="btn btn-sm btn-primary">Mark Picked Up</button></form>';
                } elseif ($order['status'] === 'picked_up') {
                    $action_btn = '<form method="POST" action="dasher-actions.php"><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="' . $order['id'] . '"><input type="hidden" name="new_status" value="delivered"><button class="btn btn-sm btn-success">Mark Delivered</button></form>';
                }
                echo '<div class="order-card"><div class="order-header"><span class="order-id">Order #' . $order['id'] . '</span><span class="order-status ' . $order['status'] . '">' . ucfirst($order['status']) . '</span></div><div class="order-details"><p><strong>Address:</strong> ' . htmlspecialchars($order['delivery_address']) . '</p></div><div class="order-actions">' . $action_btn . '</div></div>';
            }
            echo '</div>';
        }
    }
    
    // Stop execution so we only return the HTML fragment
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> 
  <title>Dashboard – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
  <script>
    // AJAX Polling: Refreshes content every 5 seconds without full page reload
    setInterval(function() {
        fetch(window.location.href.split('?')[0] + '?ajax_refresh=1')
            .then(response => response.text())
            .then(html => {
                if(html.length > 20) { // Safety check to ensure we got content
                    document.getElementById('live-content').innerHTML = html;
                }
            })
            .catch(err => console.error('Auto-refresh failed', err));
    }, 5000); 
  </script>
</head>
<body>
  <?php if ($role === 'customer' && isset($cart_count) && $cart_count > 0): ?>
    <a href="view-cart.php" class="cart-icon">🛒 Cart <span class="cart-count"><?php echo $cart_count; ?></span></a>
  <?php endif; ?>
  
  <div class="dash">
    <h2>Welcome, <?php echo htmlspecialchars($name); ?>!</h2>
    <p>Logged in as <strong><?php echo htmlspecialchars($role); ?></strong></p>
    <p><a href="/UMBC447-DOORDASH/logout.php" class="logout-link">Logout</a></p>

    <?php if (!empty($notifications)): ?>
        <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: left;">
            <h4 style="margin-top: 0;">🔔 Notifications</h4>
            <ul>
                <?php foreach ($notifications as $note): ?>
                    <li><?php echo htmlspecialchars($note['message']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="dash-content">
        
        <?php if ($role === 'dasher' && !$is_approved): ?>
            <div class="message error" style="max-width: 100%; background: #fff3cd; color: #856404; border-color: #ffeeba;">
                <h3>⚠️ Account Pending Approval</h3>
                <p>Thank you for registering! Your account is currently under review by an administrator.</p>
                <p>You will be able to accept orders once your account is approved.</p>
            </div>
        <?php else: ?>
            
            <div id="live-content">
                
                <?php if ($role === 'admin'): ?>
                    <h3>Admin Panel</h3>
                    <?php if (!empty($pending_dashers)): ?>
                        <div style="background: #fff3cd; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                            <h4>⚠️ Pending Dashers</h4>
                            <table class="admin-table">
                            <?php foreach ($pending_dashers as $dasher): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dasher['email']); ?></td>
                                    <td><form method="POST" action="admin-actions.php" style="margin:0;"><input type="hidden" name="action" value="approve_user"><input type="hidden" name="user_id" value="<?php echo $dasher['id']; ?>"><button class="btn btn-xs btn-success">Approve</button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pending_restaurants)): ?>
                        <div style="background: #d4edda; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                            <h4>🏪 Pending Restaurants</h4>
                            <table class="admin-table">
                            <?php foreach ($pending_restaurants as $owner): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($owner['email']); ?> (<?php echo htmlspecialchars($owner['venue_name'] ?? 'Unassigned'); ?>)</td>
                                    <td><form method="POST" action="admin-actions.php" style="margin:0;"><input type="hidden" name="action" value="approve_user"><input type="hidden" name="user_id" value="<?php echo $owner['id']; ?>"><button class="btn btn-xs btn-success">Approve</button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                    <h4>All Orders (Detailed Tracking)</h4>
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Restaurant</th><th>Customer</th><th>Dasher</th><th>Status</th><th>Ordered At</th><th>Delivered At</th></tr></thead>
                        <tbody>
                        <?php foreach ($all_orders as $o): ?>
                            <tr>
                                <td>#<?php echo $o['id']; ?></td>
                                <td><?php echo htmlspecialchars($o['restaurant_name']); ?></td>
                                <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                                <td><?php echo $o['dasher_name'] ?? '-'; ?></td>
                                <td><span class="order-status <?php echo $o['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $o['status'])); ?></span></td>
                                <td><?php echo date('g:i A', strtotime($o['created_at'])); ?></td>
                                <td><?php if (!empty($o['delivered_at'])) echo date('g:i A', strtotime($o['delivered_at'])); else echo '<em style="color:#999;">In Progress</em>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($role === 'customer'): ?>
                    <h3>Customer Dashboard</h3>
                    <?php if (!empty($my_orders)): ?>
                        <div class="orders-section"><h4>📦 My Orders</h4>
                        <?php foreach ($my_orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header"><span class="order-id">#<?php echo $order['id']; ?></span><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></div>
                                <div class="order-details">
                                    <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                                    <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'] + $order['tip_amount'], 2); ?></p>
                                    <p style="font-size: 0.9em; color: #555; margin-top: 5px;">
                                        <strong>Ordered:</strong> <?php echo date('M j, g:i A', strtotime($order['created_at'])); ?><br>
                                        <?php if (!empty($order['delivered_at'])): ?>
                                            <strong style="color: #28a745;">Delivered:</strong> <?php echo date('M j, g:i A', strtotime($order['delivered_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="order-actions"><a href="order-confirmation.php?orders=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">📍 Track Live Progress</a></div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <h4 style="text-align: left; margin-top: 30px;">Browse Restaurants</h4>
                    <ul class="restaurant-list">
                        <?php foreach ($restaurants as $resto): ?>
                            <li><a href="menu.php?id=<?php echo $resto['id']; ?>" class="restaurant-item-link"><img src="<?php echo htmlspecialchars($resto['image_url'] ?? 'placeholder.jpg'); ?>" alt="Img"><div><h4><?php echo htmlspecialchars($resto['name']); ?></h4><p><?php echo htmlspecialchars($resto['description']); ?></p></div></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($role === 'dasher' && $is_approved): ?>
                    <h3>Dasher Dashboard</h3>
                    <div class="availability-toggle">Status: <?php echo $dasher_availability ? '🟢 Available' : '🔴 Unavailable'; ?> <form method="POST" action="dasher-actions.php" style="margin-top: 10px;"><input type="hidden" name="action" value="toggle_availability"><button type="submit" class="btn <?php echo $dasher_availability ? 'btn-danger' : 'btn-success'; ?>"><?php echo $dasher_availability ? 'Go Offline' : 'Go Online'; ?></button></form></div>
                    <?php if ($dasher_availability && !empty($available_orders)): ?>
                        <div class="orders-section"><h4>Available Deliveries</h4>
                        <?php foreach ($available_orders as $order): ?>
                            <div class="order-card"><p><strong>Pay:</strong> $<?php echo number_format($order['total_amount'] + $order['tip_amount'], 2); ?></p><p><strong>To:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p><form method="POST" action="dasher-actions.php"><input type="hidden" name="action" value="accept_order"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><button class="btn btn-sm btn-success">Accept</button></form></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($my_orders)): ?>
                        <div class="orders-section"><h4>My Deliveries (Earnings)</h4>
                        <?php foreach ($my_orders as $order): ?>
                            <div class="order-card"><div class="order-header"><span class="order-id">Order #<?php echo $order['id']; ?></span><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></div><div class="order-details"><p><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p></div>
                            <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                <div class="order-actions">
                                <?php if ($order['status'] === 'ready'): ?>
                                    <form method="POST" action="dasher-actions.php"><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="new_status" value="picked_up"><button class="btn btn-sm btn-primary">Mark Picked Up</button></form>
                                <?php elseif ($order['status'] === 'picked_up'): ?>
                                    <form method="POST" action="dasher-actions.php"><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="new_status" value="delivered"><button class="btn btn-sm btn-success">Mark Delivered</button></form>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
            </div><?php endif; ?>
    </div>
  </div>
</body>
</html>