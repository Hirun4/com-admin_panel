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
        // $stmtDeleteItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = :order_id");
        // $stmtDeleteItems->execute([':order_id' => $order_id]);

        // $stmtInsertItems = $pdo->prepare("
        //     INSERT INTO order_items (order_id, product_id, origin_country, size, quantity, buying_price_code, buying_price, selling_price, discount)
        //     VALUES (:order_id, :product_id, :origin_country, :size, :quantity, :buying_price_code, :buying_price, :selling_price, :discount)
        // ");

        $stmtInsertItems = $pdo->prepare("
            UPDATE order_items
                SET
                    origin_country = :origin_country,
                    size = :size,
                    quantity = :quantity,
                    buying_price_code = :buying_price_code,
                    co_code = :co_code,
                    buying_price = :buying_price,
                    selling_price = :selling_price,
                    discount = :discount
                WHERE
                    order_id = :order_id AND
                    product_id = :product_id;
        ");

        foreach ($_POST['products'] as $product) {
            // Logic to determine correct buying price
            $co_code = $product['co_code'] ?? '';
            $buying_price_code = $product['buying_price_code'] ?? '';
            $buying_price = $product['buying_price'] ?? 0;

            if (!empty($co_code)) {
                // Fetch buying price from backend using co_code
                $stmtPrice = $pdo->prepare("SELECT buying_price FROM stock WHERE co_code = :co_code LIMIT 1");
                $stmtPrice->execute([':co_code' => $co_code]);
                $row = $stmtPrice->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['buying_price'])) {
                    $buying_price = $row['buying_price'];
                }
                // else keep the existing buying_price (do not set to 0)
            } else if (!empty($buying_price_code)) {
                // Fetch buying price from backend using buying_price_code
                $stmtPrice = $pdo->prepare("SELECT buying_price FROM product_codes WHERE code = :code LIMIT 1");
                $stmtPrice->execute([':code' => $buying_price_code]);
                $row = $stmtPrice->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['buying_price'])) {
                    $buying_price = $row['buying_price'];
                }
                // else keep the existing buying_price (do not set to 0)
            } else {
                // If both codes are missing, keep the existing buying_price (do not set to 0)
                // Optionally, you can fetch the current value from DB
                $stmtCurrent = $pdo->prepare("SELECT buying_price FROM order_items WHERE order_id = :order_id AND product_id = :product_id LIMIT 1");
                $stmtCurrent->execute([':order_id' => $order_id, ':product_id' => $product['product_id']]);
                $row = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['buying_price'])) {
                    $buying_price = $row['buying_price'];
                }
            }

            $stmtInsertItems->execute([
                ':order_id' => $order_id,
                ':product_id' => $product['product_id'],
                ':origin_country' => $product['origin_country'],
                ':size' => $product['size'],
                ':quantity' => $product['quantity'],
                ':buying_price_code' => $buying_price_code,
                ':co_code' => $co_code,
                ':buying_price' => $buying_price,
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .sidebar {
            min-width: 220px;
            background: #212529;
            color: #fff;
            min-height: 100vh;
        }
        .sidebar h2 {
            padding: 1.5rem 1rem 1rem 1rem;
            font-size: 1.5rem;
            border-bottom: 1px solid #343a40;
        }
        .sidebar nav a {
            display: block;
            color: #adb5bd;
            padding: 0.75rem 1rem;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        .sidebar nav a.active, .sidebar nav a:hover {
            background: #343a40;
            color: #fff;
        }
        .main-content {
            padding: 2.5rem 2rem;
            flex: 1;
        }
        .top-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
            margin-bottom: 2rem;
        }
        .top-bar .btn {
            font-size: 1rem;
        }
        .card {
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.15rem rgba(0,123,255,.15);
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
        }
        .product-entry {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
        }
        .product-entry h6 {
            font-weight: 600;
            color: #007bff;
            margin-bottom: 1rem;
        }
        .btn-primary, .btn-outline-primary {
            border-radius: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #0056b3 0%, #007bff 100%);
        }
        .btn-outline-primary {
            border: 1.5px solid #007bff;
            color: #007bff;
        }
        .btn-outline-primary:hover {
            background: #007bff;
            color: #fff;
        }
        .form-section {
            background: #f8fafc;
            border-radius: 1rem;
            padding: 2rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        @media (max-width: 991px) {
            .main-content { padding: 1rem; }
            .top-bar { font-size: 1.2rem; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 0.5rem; }
            .card { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="/project/admin/dashboard/index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <?php
                // Main admin: show all tabs
                if (isset($_SESSION['admin_logged_in']) && !isset($_SESSION['is_other_admin'])) {
                ?>
                    <a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>
                    <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                    <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                    <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                    <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                    <a href="/project/admin/dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                    <a href="/project/admin/dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                    <a href="/project/admin/dashboard/add_admin.php"><i class="fas fa-user"></i> Add Admin</a>
                <?php
                }
                // Other admin: show only allowed tabs
                elseif (isset($_SESSION['admin_logged_in']) && isset($_SESSION['is_other_admin']) && isset($_SESSION['admin_id'])) {
                    // Always show dashboard
                    $adminId = $_SESSION['admin_id'];
                    $stmt = $pdo->prepare("SELECT * FROM new_admins WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($admin) {
                        if ($admin['Manage_products'] === 'yes') {
                            echo '<a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>';
                        }
                        if ($admin['Manage_orders'] === 'yes') {
                            echo '<a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>';
                        }
                        if ($admin['Stock_Management'] === 'yes') {
                            echo '<a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>';
                        }
                        if ($admin['Manage_expence'] === 'yes') {
                            echo '<a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>';
                        }
                        if ($admin['Facebook_ads'] === 'yes') {
                            echo '<a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>';
                        }
                        if ($admin['Monthly_reports'] === 'yes') {
                            echo '<a href="/project/admin/dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>';
                        }
                        if ($admin['Resellers'] === 'yes') {
                            echo '<a href="/project/admin/dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>';
                        }
                        // Always show Add Admin for main admin only
                        // Always show Dashboard for all
                    }
                }
                ?>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>
        <div class="main-content">
            <div class="top-bar">
                <i class="fas fa-edit"></i>
                <span>Edit Order</span>
                <div class="ms-auto">
                    <a href="P_P.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-key"></i> Enter Code</a>
                    <a href="../orders/manage_orders.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="card p-4">
                        <form method="POST" class="form-container">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6 form-section">
                                    <div class="section-title"><i class="fas fa-info-circle"></i> Order Details</div>
                                    <div class="mb-3">
                                        <label for="tracking_number" class="form-label"><i class="fas fa-hashtag"></i> Tracking Number</label>
                                        <input type="text" id="tracking_number" name="tracking_number" class="form-control" value="<?= htmlspecialchars($order['tracking_number']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_name" class="form-label"><i class="fas fa-user"></i> Customer Name</label>
                                        <input type="text" id="customer_name" name="customer_name" class="form-control" value="<?= htmlspecialchars($order['customer_name']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone_number" class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                                        <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?= htmlspecialchars($order['phone_number']) ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                                        <textarea id="address" name="address" class="form-control"><?= htmlspecialchars($order['address']) ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone_number1" class="form-label"><i class="fas fa-phone"></i> Phone Number 1</label>
                                        <input type="text" id="phone_number1" name="phone_number1" class="form-control" value="<?= htmlspecialchars($order['phone_number1']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone_number2" class="form-label"><i class="fas fa-phone"></i> Phone Number 2</label>
                                        <input type="text" id="phone_number2" name="phone_number2" class="form-control" value="<?= htmlspecialchars($order['phone_number2']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="district" class="form-label"><i class="fas fa-city"></i> District</label>
                                        <input type="text" id="district" name="district" class="form-control" value="<?= htmlspecialchars($order['district']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="delivery_method" class="form-label"><i class="fas fa-truck"></i> Delivery Method</label>
                                        <select id="delivery_method" name="delivery_method" class="form-select">
                                            <option value="Home" <?= $order['delivery_method'] === 'Home' ? 'selected' : '' ?>>Home</option>
                                            <option value="Courier" <?= $order['delivery_method'] === 'Courier' ? 'selected' : '' ?>>Courier</option>
                                        </select>
                                    </div>
                                    <div id="courier-fields" style="display: none;">
                                        <div class="mb-3">
                                            <label for="delivery_fee" class="form-label"><i class="fas fa-dollar-sign"></i> Delivery Fee</label>
                                            <input type="number" id="delivery_fee" name="delivery_fee" class="form-control" value="<?= htmlspecialchars($order['delivery_fee']) ?>" step="0.01">
                                        </div>
                                        <div class="mb-3">
                                            <label for="return_reason" class="form-label"><i class="fas fa-undo"></i> Return Reason</label>
                                            <textarea id="return_reason" name="return_reason" class="form-control"><?= htmlspecialchars($order['return_reason']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label"><i class="fas fa-info-circle"></i> Status</label>
                                        <select id="status" name="status" class="form-select">
                                            <option value="Pending" <?= $order['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Shipped" <?= $order['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="Delivered" <?= $order['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="Returned" <?= $order['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                            <option value="Cancelled" <?= $order['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 form-section">
                                    <div class="section-title"><i class="fas fa-box"></i> Order Items</div>
                                    <div id="product-fields">
                                        <?php foreach ($order_items as $index => $item): ?>
                                            <div class="product-entry mb-3">
                                                <h6>Item #<?= $index + 1 ?></h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-2">
                                                        <label for="origin_country_<?= $index ?>" class="form-label"><i class="fas fa-globe"></i> Origin Country</label>
                                                        <input type="text" id="origin_country_<?= $index ?>" name="products[<?= $index ?>][origin_country]" class="form-control" value="<?= htmlspecialchars($item['origin_country']) ?>" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label for="product_id_<?= $index ?>" class="form-label"><i class="fas fa-box"></i> Product ID</label>
                                                        <input type="text" id="product_id_<?= $index ?>" name="products[<?= $index ?>][product_id]" class="form-control" value="<?= htmlspecialchars($item['product_id']) ?>" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label for="size_<?= $index ?>" class="form-label"><i class="fas fa-ruler"></i> Size</label>
                                                        <input type="text" id="size_<?= $index ?>" name="products[<?= $index ?>][size]" class="form-control" value="<?= htmlspecialchars($item['size']) ?>" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label for="quantity_<?= $index ?>" class="form-label"><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                                                        <input type="number" id="quantity_<?= $index ?>" name="products[<?= $index ?>][quantity]" class="form-control" value="<?= htmlspecialchars($item['quantity']) ?>" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label for="co_code_<?= $index ?>" class="form-label"><i class="fas fa-code"></i> Co Code</label>
                                                        <input type="text"
                                                            id="co_code_<?= $index ?>"
                                                            name="products[<?= $index ?>][co_code]"
                                                            class="form-control"
                                                            value="<?= htmlspecialchars($item['co_code'] ?? '') ?>"
                                                            onblur="fetchBuyingPriceCode(this, <?= $index ?>)" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2" style="display: none;">
                                                        <label for="buying_price_code_<?= $index ?>" class="form-label"><i class="fas fa-code"></i> Buying Price Code</label>
                                                        <input type="text"
                                                            id="buying_price_code_<?= $index ?>"
                                                            name="products[<?= $index ?>][buying_price_code]"
                                                            class="form-control"
                                                            value="<?= htmlspecialchars($item['buying_price_code'] ?? '') ?>"
                                                            readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label for="selling_price_<?= $index ?>" class="form-label"><i class="fas fa-dollar-sign"></i> Selling Price</label>
                                                        <input type="number" id="selling_price_<?= $index ?>" name="products[<?= $index ?>][selling_price]" class="form-control" value="<?= htmlspecialchars($item['selling_price']) ?>" step="0.01" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label for="discount_<?= $index ?>" class="form-label"><i class="fas fa-percent"></i> Discount</label>
                                                        <input type="number" id="discount_<?= $index ?>" name="products[<?= $index ?>][discount]" class="form-control" value="<?= htmlspecialchars($item['discount']) ?>" step="0.01" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mt-3"><i class="fas fa-save"></i> Update Order</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

        function fetchBuyingPriceCode(coCodeInput, index) {
            const coCode = coCodeInput.value.trim();
            const buyingPriceInput = document.getElementById('buying_price_code_' + index);

            if (!coCode) {
                buyingPriceInput.value = '';
                return;
            }

            fetch('add_code.php?fetch_buying_price_code=1&co_code=' + encodeURIComponent(coCode))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        buyingPriceInput.value = data.buying_price_code;
                    } else {
                        buyingPriceInput.value = 'Invalid';
                    }
                })
                .catch(() => {
                    buyingPriceInput.value = 'Error';
                });
        }
    </script>
</body>
</html>