<?php
session_start();
include_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$success = '';
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Order ID not provided.");
}

try {
    // Fetch the order and associated order items
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id");
    $stmtOrder->execute([':order_id' => $order_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    // print_r($order);
    if (!$order) {
        die("Order not found.");
    }

    $stmtOrderItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
    $stmtOrderItems->execute([':order_id' => $order_id]);
    $order_items = $stmtOrderItems->fetchAll(PDO::FETCH_ASSOC);
    print_r($order_items);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update the orders table
        $stmtUpdateOrder = $pdo->prepare("
            UPDATE orders 
            SET tracking_number = :tracking_number, 
                customer_name = :customer_name, 
                address = :address, 
                phone_number1 = :phone_number1, 
                phone_number2 = :phone_number2, 
                district = :district, 
                delivery_method = :delivery_method, 
                status = :status, 
                return_reason = :return_reason, 
                delivery_fee = :delivery_fee, 
                updated_at = NOW() 
            WHERE order_id = :order_id
        ");
        $stmtUpdateOrder->execute([
            ':tracking_number' => $_POST['tracking_number'],
            ':customer_name' => $_POST['customer_name'],
            ':address' => $_POST['address'],
            ':phone_number1' => $_POST['phone_number1'],
            ':phone_number2' => $_POST['phone_number2'],
            ':district' => $_POST['district'],
            ':delivery_method' => $_POST['delivery_method'],
            ':status' => $_POST['status'],
            ':return_reason' => $_POST['return_reason'],
            ':delivery_fee' => $_POST['delivery_fee'],
            ':order_id' => $order_id,
        ]);

        // Delete existing order items and re-insert updated items
        $stmtDeleteItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = :order_id");
        $stmtDeleteItems->execute([':order_id' => $order_id]);

        $stmtInsertItems = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, origin_country, size, quantity, buying_price_code, buying_price, selling_price, discount)
            VALUES (:order_id, :product_id, :origin_country, :size, :quantity, :buying_price_code, :buying_price, :selling_price, :discount)
        ");

        foreach ($_POST['products'] as $product) {
            $stmtInsertItems->execute([
                ':order_id' => $order_id,
                ':product_id' => $product['product_id'],
                ':origin_country' => $product['origin_country'],
                ':size' => $product['size'],
                ':quantity' => $product['quantity'],
                ':buying_price_code' => $product['buying_price_code'] ?? '',
                ':buying_price' => $product['buying_price'] ?? 0,
                ':selling_price' => $product['selling_price'],
                ':discount' => $product['discount'],
            ]);
        }

        $success = "Order updated successfully!";
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order</title>
    <link rel="stylesheet" href="../assets/css/Add_Order.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deliveryMethod = document.getElementById('delivery_method');
            const homeFields = document.querySelectorAll('.home-field');

            function toggleHomeFields() {
                if (deliveryMethod.value === 'Home') {
                    homeFields.forEach(field => field.style.display = 'none');
                } else {
                    homeFields.forEach(field => field.style.display = 'block');
                }
            }

            deliveryMethod.addEventListener('change', toggleHomeFields);
            toggleHomeFields(); // Initial check
        });
    </script>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="../dashboard/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../products/manage_products.php" ><i class="fas fa-boxes"></i> Manage Products</a>
                <a href="../orders/manage_orders.php" class="active"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        <div class="main-content">
            <div class="title">
                <h1 class="top-bar">
                    <i class="fas fa-edit"></i> Edit Order
                </h1>
                <div>
                    <a href="P_P.php"><i class="fas fa-key"></i> Enter Code</a>
                    <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                </div>
            </div>
            <div class="container2">
            <form method="POST" class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="form-group home-field">
                    <label for="tracking_number"><i class="fas fa-hashtag"></i> Tracking Number</label>
                    <input type="text" id="tracking_number" name="tracking_number" value="<?= htmlspecialchars($order['tracking_number']) ?>">
                </div>
                <div class="form-group home-field">
                    <label for="customer_name"><i class="fas fa-user"></i> Customer Name</label>
                    <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>">
                </div>
                <div class="form-group home-field">
                    <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea id="address" name="address"><?= htmlspecialchars($order['address']) ?></textarea>
                </div>
                <div class="form-group home-field">
                    <label for="phone_number1"><i class="fas fa-phone"></i> Phone Number 1</label>
                    <input type="text" id="phone_number1" name="phone_number1" value="<?= htmlspecialchars($order['phone_number1']) ?>">
                </div>
                <div class="form-group home-field">
                    <label for="phone_number2"><i class="fas fa-phone"></i> Phone Number 2</label>
                    <input type="text" id="phone_number2" name="phone_number2" value="<?= htmlspecialchars($order['phone_number2']) ?>">
                </div>
                <div class="form-group home-field">
                    <label for="district"><i class="fas fa-city"></i> District</label>
                    <input type="text" id="district" name="district" value="<?= htmlspecialchars($order['district']) ?>">
                </div>
                <div class="form-group">
                    <label for="delivery_method"><i class="fas fa-truck"></i> Delivery Method</label>
                    <select id="delivery_method" name="delivery_method">
                        <option value="Home" <?= $order['delivery_method'] === 'Home' ? 'selected' : '' ?>>Home</option>
                        <option value="Courier" <?= $order['delivery_method'] === 'Courier' ? 'selected' : '' ?>>Courier</option>
                    </select>
                </div>

                <div id="courier-fields" style="display: none;">
                    <div class="form-group">
                        <label for="delivery_fee"><i class="fas fa-dollar-sign"></i> Delivery Fee</label>
                        <input type="number" id="delivery_fee" name="delivery_fee" value="<?= htmlspecialchars($order['delivery_fee']) ?>" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="return_reason"><i class="fas fa-undo"></i> Return Reason</label>
                        <textarea id="return_reason" name="return_reason"><?= htmlspecialchars($order['return_reason']) ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                    <select id="status" name="status">
                        <option value="Shipped" <?= $order['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $order['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Returned" <?= $order['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                    </select>
                </div>

                <div id="product-fields">
                    <?php foreach ($order_items as $index => $item): ?>
                        <div class="product-entry">
                            <div class="form-group">
                                <label for="origin_country_<?= $index ?>"><i class="fas fa-globe"></i> Origin Country</label>
                                <input type="text" id="origin_country_<?= $index ?>" name="products[<?= $index ?>][origin_country]" value="<?= htmlspecialchars($item['origin_country']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="product_id_<?= $index ?>"><i class="fas fa-box"></i> Product ID</label>
                                <input type="text" id="product_id_<?= $index ?>" name="products[<?= $index ?>][product_id]" value="<?= htmlspecialchars($item['product_id']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="size_<?= $index ?>"><i class="fas fa-ruler"></i> Size</label>
                                <input type="text" id="size_<?= $index ?>" name="products[<?= $index ?>][size]" value="<?= htmlspecialchars($item['size']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="quantity_<?= $index ?>"><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                                <input type="number" id="quantity_<?= $index ?>" name="products[<?= $index ?>][quantity]" value="<?= htmlspecialchars($item['quantity']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="buying_price_code_<?= $index ?>"><i class="fas fa-code"></i> Co Code</label>
                                <input type="text" id="buying_price_code_<?= $index ?>" name="products[<?= $index ?>][buying_price_code]" value="<?= htmlspecialchars($item['buying_price_code'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="buying_price_code_<?= $index ?>"><i class="fas fa-code"></i> Buying Price Code</label>
                                <input type="text" id="buying_price_code_<?= $index ?>" name="products[<?= $index ?>][buying_price_code]" value="<?= htmlspecialchars($item['buying_price_code'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="selling_price_<?= $index ?>"><i class="fas fa-dollar-sign"></i> Selling Price</label>
                                <input type="number" id="selling_price_<?= $index ?>" name="products[<?= $index ?>][selling_price]" value="<?= htmlspecialchars($item['selling_price']) ?>" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="discount_<?= $index ?>"><i class="fas fa-percent"></i> Discount</label>
                                <input type="number" id="discount_<?= $index ?>" name="products[<?= $index ?>][discount]" value="<?= htmlspecialchars($item['discount']) ?>" step="0.01">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit"><i class="fas fa-save"></i> Update Order</button>
            </form>
            </div>
        </div>
    </div>
</body>

</html>