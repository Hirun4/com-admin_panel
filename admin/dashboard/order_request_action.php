<?php
include_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    if (!$id) {
        echo "Invalid request ID.";
        exit;
    }

    if ($action === 'accept') {
        // Fetch customer order request
        $stmt = $pdo->prepare("SELECT * FROM customer_order_request WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            echo "Order request not found.";
            exit;
        }

        // Insert into orders table
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, phone_number, address, district, delivery_fee, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'PENDING')");
        $stmt->execute([
            $request['customer_name'],
            $request['phone_number'],
            $request['address'],
            $request['district'],
            $request['delivery_fee']
        ]);
        $orderId = $pdo->lastInsertId();

        // Fetch items
        $stmt = $pdo->prepare("SELECT * FROM customer_order_request_item WHERE customer_order_request_id = ?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Insert items into order_items
        foreach ($items as $item) {
            $stmt2 = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size, quantity, buying_price, buying_price_code, discount, final_price, origin_country, phone_number, promo_price, selling_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->execute([
                $orderId,
                $item['product_id'],
                $item['size'],
                $item['quantity'],
                $item['buying_price'],
                $item['buying_price_code'],
                $item['discount'],
                $item['final_price'],
                $item['origin_country'],
                $item['phone_number'],
                $item['promo_price'],
                $item['selling_price']
            ]);
        }

        // Delete from customer_order_request and items
        $pdo->prepare("DELETE FROM customer_order_request_item WHERE customer_order_request_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM customer_order_request WHERE id = ?")->execute([$id]);

        echo "Order request accepted and moved to orders.";
        exit;
    }

    if ($action === 'reject') {
        // Delete from customer_order_request and items
        $pdo->prepare("DELETE FROM customer_order_request_item WHERE customer_order_request_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM customer_order_request WHERE id = ?")->execute([$id]);
        echo "Order request rejected and deleted.";
        exit;
    }

    echo "Invalid action.";
    exit;
}
echo "Invalid request.";
?>