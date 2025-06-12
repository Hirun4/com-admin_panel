<?php
include_once '../config/config.php';

header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? '';

if (empty($product_id) || !is_numeric($product_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Valid product ID is required.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT size, quantity 
        FROM product_stock 
        WHERE product_id = :product_id AND quantity > 0
    ");
    $stmt->execute([':product_id' => $product_id]);

    $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($sizes)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'sizes' => $sizes
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'sizes' => [],
            'message' => 'No sizes available for the selected product.'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage()
    ]);
}
?>
