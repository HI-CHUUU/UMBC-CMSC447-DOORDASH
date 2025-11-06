<?php
// Start session at the very top
session_start();

// Generate a CSRF token if one doesn't exist (for future use)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMBC447-DOORDASH Login and Register</title>
    <link rel="stylesheet" href="style.css">
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
                <select name="role" required>
                    <option value="customer">Customer</option>
                    <option value="dasher">Dasher</option>
                    <option value="restaurant">Restaurant Owner</option>
                </select>
                <button type="submit" name="Register">Register</button>
                <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
            </form>
        </div>

    </div>

    <script src="script.js"></script>
</body>

</html>
