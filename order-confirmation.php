<?php
/**
 * Order Tracking Page
 * * Displays live status of specific orders.
 * * Uses AJAX to auto-refresh status without reloading the page.
 */

// CRITICAL: Force UTF-8 Encoding
header('Content-Type: text/html; charset=utf-8');

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

// Validate Input
if (!isset($_GET['orders']) || empty($_GET['orders'])) {
    header("Location: dashboard.php");
    exit();
}

$order_ids_str = $_GET['orders'];
$order_ids = array_map('intval', explode(',', $order_ids_str));

// Helper Function to Render Order HTML (Used for both initial load and AJAX)
function render_orders($conn, $order_ids, $customer_id) {
    // Map status to progress step number
    $status_steps = [
        'pending' => 1,
        'accepted' => 2,
        'preparing' => 3,
        'ready' => 4,
        'picked_up' => 5,
        'delivered' => 6,
        'cancelled' => 0 // Special case
    ];

    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $sql = "
        SELECT o.*, r.name as restaurant_name 
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.id
        WHERE o.id IN ($placeholders) AND o.customer_id = ?
        ORDER BY o.id DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$customer_id]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $output = '';
    
    if ($result->num_rows === 0) {
        return '<p>Order not found.</p>';
    }

    while ($order = $result->fetch_assoc()) {
        // Fetch Items
        $item_res = $conn->query("SELECT * FROM order_items WHERE order_id = " . $order['id']);
        $items = $item_res->fetch_all(MYSQLI_ASSOC);
        
        $current_step = $status_steps[$order['status']] ?? 1;
        $is_cancelled = ($order['status'] === 'cancelled');
        
        $output .= '<div class="order-confirmation-card">';
        
        // Header
        $output .= '<div class="order-header">';
        $output .= '<div><span class="order-id">Order #' . $order['id'] . '</span> ';
        $output .= '<span class="order-status ' . $order['status'] . '">' . ucfirst(str_replace('_', ' ', $order['status'])) . '</span></div>';
        $output .= '<div class="order-restaurant"><strong>📍 ' . htmlspecialchars($order['restaurant_name']) . '</strong></div>';
        $output .= '</div>';

        // Progress Tracker
        if (!$is_cancelled) {
            $output .= '<div class="order-tracker"><div class="tracker-steps">';
            $steps = [
                1 => 'Placed', 2 => 'Accepted', 3 => 'Preparing', 
                4 => 'Ready', 5 => 'Picked Up', 6 => 'Delivered'
            ];
            foreach ($steps as $step_num => $label) {
                $active = ($current_step >= $step_num) ? 'active' : '';
                $output .= '<div class="tracker-step ' . $active . '">';
                $output .= '<div class="step-icon">' . $step_num . '</div>';
                $output .= '<div class="step-label">' . $label . '</div>';
                $output .= '</div>';
            }
            $output .= '</div></div>';
        } else {
            $output .= '<div class="message error" style="margin: 20px 0; text-align:center;">❌ This order has been cancelled.</div>';
        }

        // Details
        $output .= '<div class="order-details"><h4>Items Ordered:</h4><ul class="order-items-list">';
        foreach ($items as $item) {
            $output .= '<li><span>' . htmlspecialchars($item['menu_item_name']) . ' × ' . $item['quantity'] . '</span>';
            $output .= '<span>$' . number_format($item['price'] * $item['quantity'], 2) . '</span></li>';
        }
        $output .= '</ul>';
        
        // Info & Timestamps
        $output .= '<div class="order-info">';
        $output .= '<p><strong>Delivery To:</strong> ' . htmlspecialchars($order['delivery_address']) . '</p>';
        
        // Display Time History
        $output .= '<p style="margin-top:10px; font-size: 0.9em; color: #555;">';
        $output .= '🕒 <strong>Placed:</strong> ' . date('g:i A', strtotime($order['created_at'])) . '<br>';
        
        if (!empty($order['accepted_at'])) 
            $output .= '👨‍🍳 <strong>Accepted:</strong> ' . date('g:i A', strtotime($order['accepted_at'])) . '<br>';
        
        if (!empty($order['ready_at'])) 
            $output .= '🥡 <strong>Ready:</strong> ' . date('g:i A', strtotime($order['ready_at'])) . '<br>';
            
        if (!empty($order['picked_up_at'])) 
            $output .= '🚗 <strong>Picked Up:</strong> ' . date('g:i A', strtotime($order['picked_up_at'])) . '<br>';
            
        if (!empty($order['delivered_at'])) 
            $output .= '✅ <strong>Delivered:</strong> ' . date('g:i A', strtotime($order['delivered_at']));
            
        $output .= '</p></div>';

        // Total
        $output .= '<div class="order-total"><strong>Total: $' . number_format($order['total_amount'], 2) . '</strong></div>';
        $output .= '</div></div>'; // End Card
    }
    return $output;
}

// --- AJAX HANDLER ---
if (isset($_GET['ajax_refresh'])) {
    echo render_orders($conn, $order_ids, $customer_id);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Tracking – UMBC447-DOORDASH</title>
  <link rel="stylesheet" href="/UMBC447-DOORDASH/style.css">
  <script>
    // AJAX Polling: Updates only the order cards every 5 seconds
    setInterval(function() {
        const urlParams = new URLSearchParams(window.location.search);
        fetch('order-confirmation.php?ajax_refresh=1&orders=' + urlParams.get('orders'))
            .then(response => response.text())
            .then(html => {
                if(html.length > 20) {
                    document.getElementById('live-orders-container').innerHTML = html;
                }
            })
            .catch(err => console.error('Tracking refresh failed', err));
    }, 5000);
  </script>
  <style>
    .success-animation {
        text-align: center;
        padding: 30px;
        background: #d4edda;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #155724;
    }
    .success-checkmark { font-size: 50px; }
  </style>
</head>
<body>
  <div class="dash">
    <div style="text-align: left; margin-bottom: 20px;">
        <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    </div>

    <div class="dash-content">
        <div id="live-orders-container">
            <?php 
                // Initial Load
                echo render_orders($conn, $order_ids, $customer_id); 
            ?>
        </div>

        <div class="order-next-steps">
            <h4>What happens next?</h4>
            <ol style="text-align: left; padding-left: 20px; line-height: 1.8;">
                <li>The restaurant will review and accept your order.</li>
                <li>Once accepted, the kitchen will prepare your food.</li>
                <li>When ready, a dasher will be assigned to deliver your order.</li>
                <li><strong>This page updates automatically.</strong></li>
            </ol>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center;">
            <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
        </div>
    </div>
  </div>
</body>
</html>