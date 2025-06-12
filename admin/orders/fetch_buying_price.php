<?php
include_once '../config/config.php';

header('Content-Type: application/json');

$code = $_GET['code'] ?? '';

if (empty($code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Code is required.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT buying_price FROM product_codes WHERE code = :code");
    $stmt->execute([':code' => htmlspecialchars($code)]);
    $price_result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($price_result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'buying_price' => $price_result['buying_price']
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid code.'
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
