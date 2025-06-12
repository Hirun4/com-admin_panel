<?php
include_once '../config/config.php';

header('Content-Type: application/json');

// Get origin_country and category from the request
$origin_country = $_GET['origin_country'] ?? '';
$category = $_GET['category'] ?? '';

if (empty($origin_country) || empty($category)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Origin country and category are required.',
    ]);
    exit;
}

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare("
        SELECT product_id, name 
        FROM products 
        WHERE origin_country = :origin_country 
        AND category = :category 
        AND stock_status = 'In Stock'
    ");
    $stmt->execute([
        ':origin_country' => htmlspecialchars($origin_country),
        ':category' => htmlspecialchars($category),
    ]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($products)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'products' => $products,
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'products' => [],
            'message' => 'No products found for the selected country and category.',
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage(),
    ]);
}
?>
