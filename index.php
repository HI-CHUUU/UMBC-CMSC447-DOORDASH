<?php
/**
 * Landing Page / Auth Portal
 * * Purpose: Entry point for the application. Handles Login and Registration forms.
 * * Encoding: Manually sets UTF-8 header as config.php is not loaded yet.
 */

session_start();
// CRITICAL FIX: Force UTF-8 for the login page
header('Content-Type: text/html; charset=utf-8');

require 'config.php'; // Load DB connection to fetch restaurants

// Generate CSRF token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];

// Fetch Restaurants for the Registration Dropdown
$restaurants = [];
try {
    $res = $conn->query("SELECT id, name FROM restaurants ORDER BY name ASC");
    while ($row = $res->fetch_assoc()) {
        $restaurants[] = $row;
    }
} catch (Exception $e) {
    // Fail silently if DB issues, dropdown will just be empty
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMBC447-DOORDASH Login</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleRestaurantSelect() {
            var roleSelect = document.getElementById('role-select');
            var restaurantDiv = document.getElementById('restaurant-select-div');
            if (roleSelect.value === 'restaurant') {
                restaurantDiv.style.display = 'block';
                document.getElementById('restaurant-id').required = true;
            } else {
                restaurantDiv.style.display = 'none';
                document.getElementById('restaurant-id').required = false;
            }
        }
    </script>
</head>

<body>
    <div class="container">
        <?php if (isset($_GET['error'])): ?>
            <div class="message error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-box active" id="login-form">
            <form action="login-simple.php" method="POST">
                <h2>Login</h2>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                
                <button type="submit" name="Login">Login</button>
                <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
            </form>
        </div>

        <div class="form-box" id="register-form">
            <form action="register.php" method="POST">
                <h2>Register</h2>
                <input type="text" name="name" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                
                <label style="display:block; text-align:left; margin-bottom:5px; font-weight:bold;">I am a:</label>
                <select name="role" id="role-select" required onchange="toggleRestaurantSelect()" style="margin-bottom: 15px;">
                    <option value="customer">Customer</option>
                    <option value="dasher">Dasher (Requires Approval)</option>
                    <option value="restaurant">Restaurant Owner</option>
                </select>

                <div id="restaurant-select-div" style="display: none;">
                    <label style="display:block; text-align:left; margin-bottom:5px; font-weight:bold;">Select Your Restaurant:</label>
                    <select name="restaurant_id" id="restaurant-id" style="margin-bottom: 20px;">
                        <option value="">-- Choose Venue --</option>
                        <?php foreach ($restaurants as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="Register">Register</button>
                <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
            </form>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>