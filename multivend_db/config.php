<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'washing_machine');

// Service prices (Admin can change these)
define('WASHING_PRICE', 35.00);
define('TEA_PRICE', 10.00);
define('COFFEE_PRICE', 15.00);
define('BOOKING_DURATION', 90); // 1.5 hours in minutes

// System configuration
define('ADMIN_MOBILE', '01712345678');
define('SYSTEM_NAME', 'WashMate Pro');
define('CURRENCY', '৳');
define('TIMEZONE', 'Asia/Dhaka');

// API Configuration
define('API_KEY', 'washmate2024');
define('ESP32_IP', '192.168.1.100');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Common functions
function sanitize($input) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($input));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function formatCurrency($amount) {
    return CURRENCY . number_format($amount, 2);
}

function getServicePrice($service) {
    switch($service) {
        case 'washing': return WASHING_PRICE;
        case 'tea': return TEA_PRICE;
        case 'coffee': return COFFEE_PRICE;
        default: return 0;
    }
}

// Error reporting (comment in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>