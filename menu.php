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

// Check if restaurant ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$restaurant_id = (int)$_GET['id'];
$restaurant = null;
$menu_items = [];

// Fetch restaurant info
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
    die("Error fetching restaurant: " . $e->getMessage());
}

// Fetch menu items for this restaurant
try {
    $stmt = $conn->prepare("SELECT id, name, description, price, category, image_url FROM menu_items WHERE restaurant_id = ? AND available = 1 ORDER BY category, name");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
} catch (Exception $e) {
    die("Error fetching menu items: " . $e->getMessage());
}

// Group menu items by category
$grouped_items = [];
foreach ($menu_items as $item) {
    $category = $item['category'] ?? 'Other';
    if (!isset($grouped_items[$category])) {
        $grouped_items[$category] = [];
    }
    $grouped_items[$category][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Menu: <?php echo htmlspecialchars($restaurant['name']); ?> — UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
</head>
<body>

  <div class="dash">
    <!-- Back Button at the top -->
    <div style="text-align: left; margin-bottom: 20px;">
        <a href="dashboard.php" class="back-link">&larr; Back to Restaurants</a>
    </div>

    <img class="restaurant-logo-large" src="images/<?php echo htmlspecialchars($restaurant['image_url'] ?? 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($restaurant['name']); ?>">
    <h2><?php echo htmlspecialchars($restaurant['name']); ?></h2>
    <p><?php echo htmlspecialchars($restaurant['description']); ?></p>
    
    <div class="dash-content">
        <h3>Menu</h3>
        
        <?php if (empty($menu_items)): ?>
            <p style="padding: 30px; background: #f8f9fa; border-radius: 8px; margin-top: 20px;">
                No menu items are currently available for this restaurant.
            </p>
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
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Back Button at the bottom too -->
    <div style="margin-top: 30px;">
        <a href="dashboard.php" class="back-link">&larr; Back to Restaurants</a>
    </div>
  </div>
  
</body>
</html>
