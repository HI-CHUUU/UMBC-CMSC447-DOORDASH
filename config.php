<?php
// C:\xampp\htdocs\UMBC447-DOORDASH\config.php

$host = "localhost";
$user = "root";
$pass = "";               // XAMPP default
$db   = "umbc447_doordash";   // your DB name

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    //
    // THIS IS THE OTHER IMPORTANT LINE
    // The variable MUST be $conn
    //
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed.");
}