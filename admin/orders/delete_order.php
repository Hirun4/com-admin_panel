<?php
session_start();
include_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? '';

    if ($order_id) {
        try {
            $pdo->beginTransaction();

            // 1. Fetch all order items for this order
            $stmt = $pdo->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Restock each product/size
            $updateStock = $pdo->prepare("UPDATE product_stock SET quantity = quantity + :quantity WHERE product_id = :product_id AND size = :size");
            foreach ($items as $item) {
                $updateStock->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $item['product_id'],
                    ':size' => $item['size'],
                ]);
            }

            // 3. Delete order items
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $order_id]);

            // 4. Delete the order itself
            $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $order_id]);

            $pdo->commit();

            header("Location: manage_orders.php?msg=Order+deleted+and+stock+restocked");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            die("Error: " . $e->getMessage());
        }
    } else {
        die("Error: Missing order ID.");
    }
} else {
    die("Error: Invalid request method.");
}