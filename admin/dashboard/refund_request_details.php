<?php
include_once '../config/config.php';

// Fetch all refund requests
$stmt = $pdo->prepare("SELECT * FROM refund_requests ORDER BY created_at DESC");
$stmt->execute();
$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '';

if ($refunds) {
    foreach ($refunds as $refund) {
        // Fetch order items for this refund's order_id
        $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$refund['order_id']]);
        $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Fetch images for this refund request
        $stmtImgs = $pdo->prepare("SELECT image_url FROM refund_request_images WHERE refund_request_id = ?");
        $stmtImgs->execute([$refund['id']]);
        $images = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

        $html .= '<div style="border:1px solid #ccc; margin-bottom:25px; padding:15px; border-radius:8px;">';
        $html .= '<h3>Refund Request ID: ' . htmlspecialchars($refund['id']) . '</h3>';
        $html .= '<strong>Order ID:</strong> ' . htmlspecialchars($refund['order_id']) . '<br>';
        $html .= '<strong>Bank Account:</strong> ' . htmlspecialchars($refund['bank_account_number']) . '<br>';
        $html .= '<strong>Bank Branch:</strong> ' . htmlspecialchars($refund['bank_branch']) . '<br>';
        $html .= '<strong>Bank Name:</strong> ' . htmlspecialchars($refund['bank_name']) . '<br>';
        $html .= '<strong>Refund Amount:</strong> Rs. ' . htmlspecialchars($refund['refund_amount']) . '<br>';
        $html .= '<strong>Status:</strong> ' . htmlspecialchars($refund['status']) . '<br>';
        $html .= '<strong>Reason:</strong> ' . nl2br(htmlspecialchars($refund['reason'])) . '<br>';
        $html .= '<strong>Requested At:</strong> ' . htmlspecialchars($refund['created_at']) . '<br>';

        // Show order items
        if ($orderItems) {
            $html .= '<div style="margin-top:10px;"><strong>Order Items:</strong><ul>';
            foreach ($orderItems as $item) {
                $html .= '<li>Product ID: ' . htmlspecialchars($item['product_id']) . 
                         ', Size: ' . htmlspecialchars($item['size']) . 
                         ', Qty: ' . htmlspecialchars($item['quantity']) . 
                         ', Price: Rs. ' . htmlspecialchars($item['final_price']) . 
                         ', Co Code: ' . htmlspecialchars($item['co_code']) . '</li>';
            }
            $html .= '</ul></div>';
        }

        // Show images
        if ($images) {
            $html .= '<div style="margin-top:10px;"><strong>Images:</strong><br>';
            foreach ($images as $img) {
                $html .= '<img src="' . htmlspecialchars($img['image_url']) . '" alt="Refund Image" style="max-width:120px; max-height:120px; margin:5px; border:1px solid #ddd; border-radius:4px;">';
            }
            $html .= '</div>';
        }

        $html .= '<div style="margin-top:15px;">';
        if ($refund['status'] === 'PENDING') {
            $html .= '<button class="accept-refund-btn" data-id="' . htmlspecialchars($refund['id']) . '" data-order-id="' . htmlspecialchars($refund['order_id']) . '" data-amount="' . htmlspecialchars($refund['refund_amount']) . '">Accept</button> ';
            $html .= '<button class="reject-refund-btn" data-id="' . htmlspecialchars($refund['id']) . '">Reject</button>';
        } else {
            $html .= '<span style="color:gray;">Action: ' . htmlspecialchars($refund['status']) . '</span>';
        }
        $html .= '</div>';

        $html .= '</div>';
    }
} else {
    $html = '<p>No refund requests found.</p>';
}

echo $html;
?>

