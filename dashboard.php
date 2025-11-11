<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /UMBC447-DOORDASH/index.php?error=Please+login");
    exit();
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];

// Redirect restaurant owners to their dashboard
if ($role === 'restaurant') {
    header("Location: /UMBC447-DOORDASH/restaurant-dashboard.php");
    exit();
}

// Initialize variables
$restaurants = [];
$my_orders = [];
$available_orders = [];
$dasher_availability = false;
$all_orders = [];
$all_users = [];

// CUSTOMER: Fetch restaurants
if ($role === 'customer') {
    // Get cart item count
    $cart_count = 0;
    try {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $cart_count = (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        // Ignore cart count errors
    }
    
    try {
        $result = $conn->query("SELECT id, name, description, image_url FROM restaurants");
        while ($row = $result->fetch_assoc()) {
            $restaurants[] = $row;
        }
    } catch (Exception $e) {
        die("Error fetching restaurants: " . $e->getMessage());
    }
    
    // Fetch customer's orders
    try {
        $stmt = $conn->prepare("
            SELECT o.*, r.name as restaurant_name 
            FROM orders o 
            JOIN restaurants r ON o.restaurant_id = r.id 
            WHERE o.customer_id = ? 
            ORDER BY o.created_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $my_orders[] = $row;
        }
    } catch (Exception $e) {
        // Ignore if orders table doesn't exist yet
    }
}

// DASHER: Fetch availability and available orders
if ($role === 'dasher') {
    // Check dasher availability
    try {
        $stmt = $conn->prepare("SELECT is_available FROM dasher_availability WHERE dasher_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dasher_availability = (bool)$row['is_available'];
        } else {
            // Create availability record if doesn't exist
            $stmt = $conn->prepare("INSERT INTO dasher_availability (dasher_id, is_available) VALUES (?, FALSE)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }
    
    // Fetch available orders (pending or accepted without dasher)
    try {
        $stmt = $conn->prepare("
            SELECT o.*, r.name as restaurant_name, u.name as customer_name 
            FROM orders o 
            JOIN restaurants r ON o.restaurant_id = r.id 
            JOIN users u ON o.customer_id = u.id 
            WHERE (o.status = 'pending' OR o.status = 'ready') AND o.dasher_id IS NULL 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $available_orders[] = $row;
        }
    } catch (Exception $e) {
        // Ignore if orders table doesn't exist
    }
    
    // Fetch dasher's accepted orders
    try {
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
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $my_orders[] = $row;
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// ADMIN: Fetch all orders and users
if ($role === 'admin') {
    // Fetch all orders
    try {
        $stmt = $conn->prepare("
            SELECT o.*, r.name as restaurant_name, u.name as customer_name, d.name as dasher_name 
            FROM orders o 
            JOIN restaurants r ON o.restaurant_id = r.id 
            JOIN users u ON o.customer_id = u.id 
            LEFT JOIN users d ON o.dasher_id = d.id 
            ORDER BY o.created_at DESC 
            LIMIT 50
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $all_orders[] = $row;
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    // Fetch all users
    try {
        $result = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 50");
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    } catch (Exception $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard â€” UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>
  <?php if ($role === 'customer' && isset($cart_count) && $cart_count > 0): ?>
    <a href="view-cart.php" class="cart-icon">
        🛒 Cart <span class="cart-count"><?php echo $cart_count; ?></span>
    </a>
  <?php endif; ?>
  
  <div class="dash">
    <h2>Welcome, <?php echo htmlspecialchars($name); ?>!</h2>
    <p>You are logged in as <strong><?php echo htmlspecialchars($role); ?></strong></p>
    <p><a href="/UMBC447-DOORDASH/logout.php" class="logout-link">Logout</a></p>

    <div class="dash-content">
        <?php if ($role === 'customer'): ?>
            <h3>Customer Dashboard</h3>
            <p>Browse restaurants and place your order!</p>

            <?php if (!empty($my_orders)): ?>
                <div class="orders-section" style="margin-bottom: 40px;">
                    <h4 style="text-align: left; font-size: 22px; margin-bottom: 15px;">📦 My Orders</h4>
                    <?php foreach ($my_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <span class="order-id">Order #<?php echo $order['id']; ?></span>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php 
                                    $status_icons = [
                                        'pending' => '⏳',
                                        'accepted' => '✓',
                                        'preparing' => '👨‍🍳',
                                        'ready' => '🔔',
                                        'picked_up' => '🚗',
                                        'delivered' => '✓',
                                        'cancelled' => '✗'
                                    ];
                                    $icon = $status_icons[$order['status']] ?? '';
                                    echo $icon . ' ' . ucfirst(str_replace('_', ' ', $order['status'])); 
                                    ?>
                                </span>
                            </div>
                            <div class="order-details">
                                <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                                <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                                <p><strong>Ordered:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                                
                                <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                    <!-- Order Status Tracker -->
                                    <div class="order-tracker-mini">
                                        <div class="tracker-steps-mini">
                                            <div class="tracker-step-mini <?php echo ($order['status'] == 'pending' || $order['status'] == 'accepted' || $order['status'] == 'preparing' || $order['status'] == 'ready' || $order['status'] == 'picked_up' || $order['status'] == 'delivered') ? 'active' : ''; ?>">
                                                <div class="step-icon-mini">1</div>
                                                <div class="step-label-mini">Placed</div>
                                            </div>
                                            <div class="tracker-step-mini <?php echo ($order['status'] == 'accepted' || $order['status'] == 'preparing' || $order['status'] == 'ready' || $order['status'] == 'picked_up' || $order['status'] == 'delivered') ? 'active' : ''; ?>">
                                                <div class="step-icon-mini">2</div>
                                                <div class="step-label-mini">Accepted</div>
                                            </div>
                                            <div class="tracker-step-mini <?php echo ($order['status'] == 'preparing' || $order['status'] == 'ready' || $order['status'] == 'picked_up' || $order['status'] == 'delivered') ? 'active' : ''; ?>">
                                                <div class="step-icon-mini">3</div>
                                                <div class="step-label-mini">Preparing</div>
                                            </div>
                                            <div class="tracker-step-mini <?php echo ($order['status'] == 'ready' || $order['status'] == 'picked_up' || $order['status'] == 'delivered') ? 'active' : ''; ?>">
                                                <div class="step-icon-mini">4</div>
                                                <div class="step-label-mini">Ready</div>
                                            </div>
                                            <div class="tracker-step-mini <?php echo ($order['status'] == 'picked_up' || $order['status'] == 'delivered') ? 'active' : ''; ?>">
                                                <div class="step-icon-mini">5</div>
                                                <div class="step-label-mini">Pickup</div>
                                            </div>
                                            <div class="tracker-step-mini <?php echo ($order['status'] == 'delivered') ? 'active' : ''; ?>">
                                                <div class="step-icon-mini">6</div>
                                                <div class="step-label-mini">Delivered</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($restaurants)): ?>
                <p>No restaurants are available at this time.</p>
            <?php else: ?>
                <h4 style="text-align: left; font-size: 22px; margin-bottom: 15px;">Browse Restaurants</h4>
                <ul class="restaurant-list">
                    <?php foreach ($restaurants as $resto): ?>
                        <li>
                            <a href="menu.php?id=<?php echo $resto['id']; ?>" class="restaurant-item-link">
                                <img src="<?php echo htmlspecialchars($resto['image_url'] ?? 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($resto['name']); ?>">
                                <div>
                                    <h4><?php echo htmlspecialchars($resto['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($resto['description']); ?></p>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php elseif ($role === 'dasher'): ?>
            <h3>Dasher Dashboard</h3>
            
            <!-- Availability Toggle -->
            <div class="availability-toggle">
                <div class="availability-status <?php echo $dasher_availability ? 'available' : 'unavailable'; ?>">
                    Status: <?php echo $dasher_availability ? 'ðŸŸ¢ Available' : 'ðŸ”´ Unavailable'; ?>
                </div>
                <form method="POST" action="update-availability.php" style="margin-top: 15px;">
                    <button type="submit" name="toggle_availability" class="btn <?php echo $dasher_availability ? 'btn-danger' : 'btn-success'; ?>">
                        <?php echo $dasher_availability ? 'Go Offline' : 'Go Online'; ?>
                    </button>
                </form>
            </div>

            <!-- My Deliveries -->
            <?php if (!empty($my_orders)): ?>
                <div class="orders-section" style="margin-bottom: 40px;">
                    <h4 style="text-align: left; font-size: 22px; margin-bottom: 15px;">My Deliveries</h4>
                    <?php foreach ($my_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <span class="order-id">Order #<?php echo $order['id']; ?></span>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="order-details">
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                                <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                            </div>
                            <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                <div class="order-actions">
                                    <?php if ($order['status'] === 'ready'): ?>
                                        <form method="POST" action="update-order-status.php" style="display: inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="new_status" value="picked_up">
                                            <button type="submit" class="btn btn-sm btn-primary">Mark as Picked Up</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($order['status'] === 'picked_up'): ?>
                                        <form method="POST" action="update-order-status.php" style="display: inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="new_status" value="delivered">
                                            <button type="submit" class="btn btn-sm btn-success">Mark as Delivered</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Available Orders -->
            <?php if ($dasher_availability && !empty($available_orders)): ?>
                <div class="orders-section">
                    <h4 style="text-align: left; font-size: 22px; margin-bottom: 15px;">Available Deliveries</h4>
                    <?php foreach ($available_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <span class="order-id">Order #<?php echo $order['id']; ?></span>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="order-details">
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                                <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                            </div>
                            <div class="order-actions">
                                <form method="POST" action="accept-order.php">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Accept Delivery</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($dasher_availability && empty($available_orders)): ?>
                <p style="padding: 30px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    No deliveries available right now. Check back soon!
                </p>
            <?php elseif (!$dasher_availability): ?>
                <p style="padding: 30px; background: #fff3cd; border-radius: 8px; text-align: center; color: #856404;">
                    You are currently offline. Toggle your availability to see delivery opportunities!
                </p>
            <?php endif; ?>

        <?php elseif ($role === 'admin'): ?>
            <h3>Admin Panel</h3>
            
            <!-- All Orders -->
            <?php if (!empty($all_orders)): ?>
                <div style="margin-bottom: 40px;">
                    <h4 style="text-align: left; font-size: 22px; margin-bottom: 15px;">Recent Orders</h4>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Restaurant</th>
                                <th>Dasher</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['restaurant_name']); ?></td>
                                    <td><?php echo $order['dasher_name'] ? htmlspecialchars($order['dasher_name']) : 'Unassigned'; ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- All Users -->
            <?php if (!empty($all_users)): ?>
                <div>
                    <h4 style="text-align: left; font-size: 22px; margin-bottom: 15px;">Recent Users</h4>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="padding: 30px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    Admin panel data will appear here once orders and users are created.
                </p>
            <?php endif; ?>

        <?php endif; ?>
    </div>
  </div>
</body>
</html>
