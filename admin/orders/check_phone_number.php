<?php
include_once '../config/config.php';

$phone_number = $_GET['phone_number'] ?? '';

$response = ['exists' => false, 'promo_price' => 0];

if ($phone_number) {
    $stmt = $pdo->prepare("SELECT promo_price FROM order_items WHERE phone_number = :phone_number  ORDER BY order_id DESC LIMIT 1");
    $stmt->execute([':phone_number' => $phone_number]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $response['exists'] = true;
        $response['promo_price'] = $result['promo_price'];
    }
}

echo json_encode($response);
