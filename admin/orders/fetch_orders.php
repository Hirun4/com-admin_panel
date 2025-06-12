<?php
session_start();
include_once '../config/config.php';

$search_keyword = $_POST['search'] ?? '';

try {
    $query = "
        SELECT 
            o.order_id,
            o.tracking_number,
            o.customer_name,
            o.address,
            o.phone_number1,
            o.phone_number2,
            o.district,
            o.status,
            o.created_at,
            GROUP_CONCAT(p.name SEPARATOR '<br>') AS product_names,
            GROUP_CONCAT(oi.size SEPARATOR '<br>') AS sizes,
            GROUP_CONCAT(oi.quantity SEPARATOR '<br>') AS quantities
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.tracking_number LIKE :search
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $search_term = "%{$search_keyword}%";
    $stmt->bindParam(':search', $search_term);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($orders)) {
        foreach ($orders as $order) {
            echo "<tr>
                <td>{$order['order_id']}</td>
                <td>{$order['customer_name']}</td>
                <td>{$order['product_names']}</td>
                <td>{$order['tracking_number']}</td>
                <td>{$order['sizes']}</td>
                <td>{$order['quantities']}</td>
                <td>{$order['address']}</td>
                <td>{$order['phone_number1']}</td>
                <td>{$order['phone_number2']}</td>
                <td>{$order['district']}</td>
                <td>{$order['status']}</td>
                <td>{$order['created_at']}</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='12'>No orders found.</td></tr>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
