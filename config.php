<?php
// MAMP Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wod_platform');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // MAMP default password

// Start session
session_start();

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['username']);
}

// Get current user
function getCurrentUser() {
    return $_SESSION['username'] ?? null;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}
?>