<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? '';
    $order_id = $_POST['order_id'] ?? null;
    $refund_amount = floatval($_POST['refund_amount'] ?? 0);

    if (!$id) {
        echo "Invalid request.";
        exit;
    }

    if ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE refund_requests SET status = 'REJECTED' WHERE id = ?");
        $stmt->execute([$id]);
        echo "Refund request rejected.";
        exit;
    }

    if ($action === 'accept') {
        // Get all order_items for this order
        $stmt = $pdo->prepare("SELECT oi.*, pc.buying_price FROM order_items oi LEFT JOIN product_codes pc ON oi.buying_price_code = pc.code WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total buying price for all items in the order
        $totalBuyingPrice = 0;
        foreach ($orderItems as $item) {
            $totalBuyingPrice += floatval($item['buying_price']) * intval($item['quantity']);
        }

        // Deduct refund_amount from total revenue and (refund_amount - totalBuyingPrice) from profit
        // You may want to store these adjustments in a separate table or recalculate on the fly.
        // For now, just update the refund status.
        $stmt = $pdo->prepare("UPDATE refund_requests SET status = 'APPROVED' WHERE id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("SELECT status FROM refund_requests WHERE id = ?");
        $stmt->execute([$id]);
        $after = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Status after update: " . $after['status'] . "\n";

        echo "Refund request approved. Revenue and profit will be adjusted in reports.";
        exit;
    }
}
echo "Invalid action.";