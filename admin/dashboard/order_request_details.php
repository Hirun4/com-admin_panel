<?php
// This script displays details of customer order requests in a dashboard format.
include_once '../config/config.php';

// Fetch all customer order requests
$stmt = $pdo->query("SELECT * FROM customer_order_request ORDER BY created_at DESC");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$requests) {
    echo "<p>No order requests found.</p>";
    exit;
}

foreach ($requests as $req) {
    echo "<div style='border-bottom:1px solid #ccc; margin-bottom:15px; padding-bottom:10px;'>";
    echo "<strong>Customer Name:</strong> " . htmlspecialchars($req['customer_name']) . "<br>";
    echo "<strong>Phone:</strong> " . htmlspecialchars($req['phone_number']) . "<br>";
    echo "<strong>Address:</strong> " . htmlspecialchars($req['address']) . "<br>";
    echo "<strong>District:</strong> " . htmlspecialchars($req['district']) . "<br>";
    echo "<strong>Date:</strong> " . htmlspecialchars($req['created_at']) . "<br>";
    echo "<strong>Status:</strong> " . htmlspecialchars($req['status']) . "<br>";

    // Fetch items for this request
    $stmt2 = $pdo->prepare("SELECT * FROM customer_order_request_item WHERE customer_order_request_id = ?");
    $stmt2->execute([$req['id']]);
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if ($items) {
        echo "<table style='width:100%; margin-top:10px; border-collapse:collapse;'>";
        echo "<tr>
                <th>Product</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Final Price</th>
                <th>Promo Price</th>
                <th>Selling Price</th>
              </tr>";
        foreach ($items as $item) {
            // Fetch product name
            $stmt3 = $pdo->prepare("SELECT name FROM products WHERE product_id = ?");
            $stmt3->execute([$item['product_id']]);
            $product = $stmt3->fetch(PDO::FETCH_ASSOC);
            $productName = $product ? $product['name'] : 'Unknown';

            echo "<tr>";
            echo "<td>" . htmlspecialchars($productName) . "</td>";
            echo "<td>" . htmlspecialchars($item['size']) . "</td>";
            echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
            echo "<td>" . htmlspecialchars($item['final_price']) . "</td>";
            echo "<td>" . htmlspecialchars($item['promo_price']) . "</td>";
            echo "<td>" . htmlspecialchars($item['selling_price']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<em>No items found for this request.</em>";
    }

    // Accept and Reject buttons (no backend action yet)
    echo "<div style='margin-top:10px;'>";
    echo "<button class='accept-btn' data-id='" . $req['id'] . "' style='background:#27ae60;color:#fff;padding:6px 16px;border:none;border-radius:4px;cursor:pointer;margin-right:10px;'>Accept</button>";
    echo "<button class='reject-btn' data-id='" . $req['id'] . "' style='background:#e74c3c;color:#fff;padding:6px 16px;border:none;border-radius:4px;cursor:pointer;'>Reject</button>";
    echo "</div>";

    echo "</div>";
}
