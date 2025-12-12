<?php
/**
 * Restaurant Menu Controller
 * * Displays menu items and handles cart interactions.
 * * Fixes: Added UTF-8 Meta tags and config.php inclusion.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: /UMBC447-DOORDASH/index.php?error=Please+login");
    exit();
}

require 'config.php';

// Validate Input
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$restaurant_id = (int)$_GET['id'];
$restaurant = null;
$menu_items = [];

// Fetch Restaurant Info
try {
    $stmt = $conn->prepare("SELECT id, name, description, image_url FROM restaurants WHERE id = ?");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $restaurant = $result->fetch_assoc();
    } else {
        header("Location: dashboard.php?error=Restaurant+not+found");
        exit();
    }
} catch (Exception $e) {
    die("Database Error");
}

// Fetch Menu Items
try {
    $stmt = $conn->prepare("SELECT id, name, description, price, category, image_url FROM menu_items WHERE restaurant_id = ? AND available = 1 ORDER BY category, name");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $menu_items[] = $row;
} catch (Exception $e) {
    die("Database Error");
}

// Group by Category
$grouped_items = [];
foreach ($menu_items as $item) {
    $category = $item['category'] ?? 'Other';
    if (!isset($grouped_items[$category])) $grouped_items[$category] = [];
    $grouped_items[$category][] = $item;
}

// Cart Count
$cart_count = 0;
if ($_SESSION['role'] === 'customer') {
    try {
        $customer_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $cart_count = (int)($row['total'] ?? 0);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <title>Menu: <?php echo htmlspecialchars($restaurant['name']); ?> — UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>

  <?php if ($_SESSION['role'] === 'customer' && $cart_count > 0): ?>
    <a href="view-cart.php" class="cart-icon">
        🛒 Cart <span class="cart-count"><?php echo $cart_count; ?></span>
    </a>
  <?php endif; ?>

  <div class="dash">
    <div style="text-align: left; margin-bottom: 20px;">
        <a href="dashboard.php" class="back-link">&larr; Back to Restaurants</a>
    </div>

    <img class="restaurant-logo-large" src="images/<?php echo htmlspecialchars($restaurant['image_url'] ?? 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($restaurant['name']); ?>">
    <h2><?php echo htmlspecialchars($restaurant['name']); ?></h2>
    <p><?php echo htmlspecialchars($restaurant['description']); ?></p>

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
        <h3>Menu</h3>
        
        <?php if (empty($menu_items)): ?>
            <p style="padding: 30px; background: #f8f9fa; border-radius: 8px;">No menu items available.</p>
        <?php else: ?>
            <?php foreach ($grouped_items as $category => $items): ?>
                <div class="menu-category">
                    <h4 class="category-title"><?php echo htmlspecialchars($category); ?></h4>
                    <ul class="menu-items-list">
                        <?php foreach ($items as $item): ?>
                            <li class="menu-item">
                                <div class="menu-item-info">
                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="item-description"><?php echo htmlspecialchars($item['description']); ?></span>
                                </div>
                                <span class="item-price">$<?php echo number_format($item['price'], 2); ?></span>
                                <?php if ($_SESSION['role'] === 'customer'): ?>
                                    <div class="menu-item-actions">
                                        <form method="POST" action="cart-handler.php" style="display: inline;">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="restaurant_id" value="<?php echo $restaurant_id; ?>">
                                            <input type="number" name="quantity" value="1" min="1" max="99" class="quantity-input">
                                            <button type="submit" class="btn btn-sm btn-success">Add to Cart</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="dashboard.php" class="back-link">&larr; Back to Restaurants</a>
    </div>
  </div>
</body>
</html>