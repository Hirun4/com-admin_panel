<?php
session_start();
include_once '../config/config.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['product_id'])) {
    header("Location: manage_products.php");
    exit;
}

$product_id = $_GET['product_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: manage_products.php?status=deleted");
    exit;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
