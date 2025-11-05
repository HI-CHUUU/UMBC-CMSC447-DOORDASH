<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a dasher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dasher') {
    header("Location: /UMBC447-DOORDASH/dashboard.php?error=Unauthorized");
    exit();
}

require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $dasher_id = $_SESSION['user_id'];
    
    try {
        // Get current availability
        $stmt = $conn->prepare("SELECT is_available FROM dasher_availability WHERE dasher_id = ?");
        $stmt->bind_param("i", $dasher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Toggle existing availability
            $row = $result->fetch_assoc();
            $new_status = !$row['is_available'];
            
            $stmt = $conn->prepare("UPDATE dasher_availability SET is_available = ? WHERE dasher_id = ?");
            $stmt->bind_param("ii", $new_status, $dasher_id);
            $stmt->execute();
        } else {
            // Create new availability record (set to available)
            $stmt = $conn->prepare("INSERT INTO dasher_availability (dasher_id, is_available) VALUES (?, TRUE)");
            $stmt->bind_param("i", $dasher_id);
            $stmt->execute();
        }
        
        header("Location: /UMBC447-DOORDASH/dashboard.php?success=Availability+updated");
        exit();
    } catch (Exception $e) {
        header("Location: /UMBC447-DOORDASH/dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: /UMBC447-DOORDASH/dashboard.php");
exit();
