<?php
session_start();
include_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['phone_number']) || empty($_GET['phone_number'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Phone number is required']);
    exit;
}

$phoneNumber = $_GET['phone_number'];

try {
    // Check if the phone number exists in previous orders and has a promo price
    $stmt = $pdo->prepare("
        SELECT SUM(promo_price) as total_promo_price 
        FROM order_items 
        WHERE phone_number = :phone_number
    ");
    $stmt->execute([':phone_number' => $phoneNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['total_promo_price'] > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'exists' => true,
            'promo_price' => $result['total_promo_price']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'exists' => false,
            'promo_price' => 0
        ]);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}