<?php
// Database configuration
$host = 'localhost';       // Database host (usually 'localhost')
$db = 'ecommerce_db';      // Your database name
$user = 'root';            // Database username (default for XAMPP/WAMP is 'root')
$pass = '';                // Database password (default for XAMPP/WAMP is '')

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$host;port=3307;dbname=$db;charset=utf8", $user, $pass);

    // Set error mode to throw exceptions for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Display a connection error message and terminate the script
    die("Connection failed: " . $e->getMessage());
}
