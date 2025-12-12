<?php
/**
 * Configuration Module
 * * Purpose: Bootstraps the application environment and handles database connectivity.
 * * Encoding Strategy: Explicitly forces UTF-8 headers to prevent character corruption (Mojibake).
 */

// CRITICAL FIX: Force the browser to treat all output as UTF-8
header('Content-Type: text/html; charset=utf-8');

$host = "localhost";
$user = "root";
$pass = "";               
$db   = "umbc447_doordash";   

// Enable strict error reporting for robust debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    // Set the database connection to support Emojis and Unicode
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("System Error: Database connection failed. Please check credentials.");
}

// Approved Delivery Zones
// Comprehensive list of UMBC buildings based on Student Housing Map
$campus_locations = [
    // Residential Halls
    "Chesapeake Hall",
    "Erickson Hall",
    "Harbor Hall",
    "Patapsco Hall",
    "Potomac Hall",
    "Susquehanna Hall",
    "Walker Avenue Apartments",

    // Hillside Apartments
    "Hillside: Breton Hall",
    "Hillside: Casselman Hall",
    "Hillside: Deep Creek Hall",
    "Hillside: Elk Hall",
    "Hillside: Manokin Hall",
    "Hillside: Patuxent Hall",
    "Hillside: Pocomoke Hall",
    "Hillside: Sideling Hall",

    // Terrace Apartments
    "Terrace: Antietam Hall",
    "Terrace: Chincoteague Hall",
    "Terrace: Gunpowder Hall",
    "Terrace: Monocacy Hall",
    "Terrace: Nanticoke Hall",
    "Terrace: Sassafras Hall",
    "Terrace: Tuckahoe Hall",
    "Terrace: Wicomico Hall",

    // West Hill Apartments
    "West Hill: Chester Hall",
    "West Hill: Choptank Hall",
    "West Hill: Magothy Hall",
    "West Hill: Tangier Hall",
    "West Hill: Wye Hall",

    // Academic & Administrative Buildings
    "Academic Services Building",
    "Administration Building",
    "Albin O. Kuhn Library",
    "Biological Sciences Building",
    "Central Plant",
    "The Commons",
    "Engineering Building",
    "Facilities Management",
    "Fine Arts Building",
    "Information Technology/Engineering (ITE)",
    "Interdisciplinary Life Sciences Building (ILSB)",
    "Math & Psychology Building",
    "Meyerhoff Chemistry Building",
    "Performing Arts & Humanities Building (PAHB)",
    "Physics Building",
    "Public Policy Building",
    "Retriever Activities Center (RAC)",
    "Sherman Hall",
    "Sondheim Hall",
    "Technology Research Center (TRC)",
    "True Grit's Dining Hall",
    "UMBC Event Center",
    "University Center"
];

sort($campus_locations); // Ensure the dropdown is always alphabetical

/**
 * Utility: send_notification
 * * Helper to queue user alerts.
 */
function send_notification($conn, $user_id, $message) {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $message);
        $stmt->execute();
    } catch (Exception $e) {
        // Suppress failures to avoid blocking the main thread
    }
}
?>