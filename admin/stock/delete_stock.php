<?php
session_start();
include_once '../config/config.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if the stock_id is provided in the URL
if (!isset($_GET['stock_id'])) {
    header("Location: stock_management.php");
    exit;
}

$stock_id = intval($_GET['stock_id']);

// Handle stock deletion
try {
    // Check if the stock exists
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE id = ?");
    $stmt->execute([$stock_id]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock) {
        header("Location: stock_management.php?message=Stock not found.");
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Delete investments related to the stock
    $stmt = $pdo->prepare("DELETE FROM investments WHERE stock_id = ?");
    $stmt->execute([$stock_id]);

    // Delete the stock itself
    $stmt = $pdo->prepare("DELETE FROM stock WHERE id = ?");
    $stmt->execute([$stock_id]);

    // Commit transaction
    $pdo->commit();

    header("Location: stock_management.php?message=Stock deleted successfully.");
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>
