<?php
include_once '../config/config.php';

header('Content-Type: application/json');

$country = $_GET['country'] ?? '';

if (empty($country)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Origin country is required.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT category 
        FROM products 
        WHERE origin_country = :country
    ");
    $stmt->execute([':country' => htmlspecialchars($country)]);

    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($categories)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'categories' => [],
            'message' => 'No categories found for the selected country.'
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
