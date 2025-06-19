<?php
session_start();
include_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$success = '';

// Fetch countries for the dropdown
try {
    $countries = ['Vietnam', 'China', 'India', 'Local'];
} catch (PDOException $e) {
    $error = 'Failed to fetch data: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_method = $_POST['delivery_method'] ?? 'Home';
    $tracking_number = ($delivery_method === 'Courier') ? ($_POST['tracking_number'] ?? uniqid('TRK_', true)) : null;
    $customer_name = ($delivery_method === 'Courier') ? $_POST['customer_name'] ?? '' : null;
    $address = $_POST['address'] ?? '';
    $phone_number1 = $_POST['phone_number1'] ?? '';
    $phone_number2 = ($delivery_method === 'Courier') ? ($_POST['phone_number2'] ?? '') : null;
    $payment_method = ($delivery_method === 'Courier') ? ($_POST['payment'] ?? '') : null;
    $district = $_POST['district'] ?? '';
    $delivery_fee = $_POST['delivery_fee'] ?? 0.00;
    if ($delivery_fee === '' || $delivery_fee === null) {
        $delivery_fee = 0.00;
    }
    $status = $_POST['status'] ?? 'Pending';
    $return_reason = ($status === 'Returned') ? $_POST['return_reason'] ?? '' : null;
    $products = $_POST['products'] ?? [];
    $use_existing_promo = isset($_POST['use_existing_promo']);
    $existing_promo_price = $_POST['existing_promo_price'] ?? 0;
    $phone_number = $_POST['phone_number'] ?? '';

    if (empty($products)) {
        $error = 'No products selected!';
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            // Insert order details
            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (tracking_number, customer_name, address, phone_number, phone_number1, phone_number2, district, delivery_method, status, payment_method, return_reason, delivery_fee, created_at, updated_at)
                VALUES (:tracking_number, :customer_name, :address, :phone_number, :phone_number1, :phone_number2, :district, :delivery_method, :status, :payment_method, :return_reason, :delivery_fee, NOW(), NOW())
            ");
            $stmtOrder->execute([
                ':tracking_number' => $tracking_number,
                ':customer_name' => $customer_name,
                ':address' => $address,
                ':phone_number' => $phone_number,
                ':phone_number1' => $phone_number1,
                ':phone_number2' => $phone_number2,
                ':district' => $district,
                ':delivery_method' => $delivery_method,
                ':status' => $status,
                ':payment_method' => $payment_method,
                ':return_reason' => $return_reason,
                ':delivery_fee' => $delivery_fee,

            ]);
            $order_id = $pdo->lastInsertId();

            // Prepare statements for order items and stock updates
            $stmtOrderItems = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, origin_country, size, quantity, buying_price_code, buying_price, selling_price, discount, promo_price, final_price, phone_number, co_code)
                VALUES (:order_id, :product_id, :origin_country, :size, :quantity, :buying_price_code, :buying_price, :selling_price, :discount, :promo_price, :final_price, :phone_number, :co_code)
            ");
            $stmtUpdateStock = $pdo->prepare("
                UPDATE product_stock 
                SET quantity = quantity - :quantity 
                WHERE product_id = :product_id AND size = :size AND quantity >= :quantity
            ");
            $stmtUpdateProduct = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = (
                    SELECT SUM(quantity) FROM product_stock WHERE product_id = :product_id
                ),
                stock_status = CASE 
                    WHEN (SELECT SUM(quantity) FROM product_stock WHERE product_id = :product_id) > 0 THEN 'In Stock'
                    ELSE 'Out of Stock'
                END
                WHERE product_id = :product_id
            ");

            // Process each product
            foreach ($products as $product) {
                $buying_price_code = $product['buying_price_code'] ?? null;
                $buying_price = $product['buying_price'] ?? 0;

                if ($buying_price_code) {
                    $stmtPrice = $pdo->prepare("SELECT buying_price FROM product_codes WHERE code = :code");
                    $stmtPrice->execute([':code' => $buying_price_code]);
                    $price_result = $stmtPrice->fetch(PDO::FETCH_ASSOC);

                    if ($price_result) {
                        $buying_price = $price_result['buying_price'];
                    } else {
                        $error = "Invalid buying price code!";
                        break;
                    }
                }

                $promo_price = $product['promo_price'];
                $final_price = $product['final_price'];

                $stmtOrderItems->execute([
                    ':order_id' => $order_id,
                    ':product_id' => $product['product_id'],
                    ':origin_country' => $product['origin_country'],
                    ':size' => $product['size'],
                    ':quantity' => $product['quantity'],
                    ':buying_price_code' => $buying_price_code,
                    ':buying_price' => $buying_price,
                    ':selling_price' => $product['selling_price'],
                    ':discount' => $product['discount'],
                    ':promo_price' => $promo_price,
                    ':final_price' => $final_price,
                    ':phone_number' => $phone_number,
                    ':co_code' => $product['co_code'] ?? null, // <-- Add this line
                ]);
                $stmtUpdateStock->execute([
                    ':quantity' => $product['quantity'],
                    ':product_id' => $product['product_id'],
                    ':size' => $product['size'],
                ]);

                if ($stmtUpdateStock->rowCount() === 0) {
                    $error = "Insufficient stock for product ID: {$product['product_id']} and size: {$product['size']}!";
                    $pdo->rollBack();
                    break;
                }

                $stmtUpdateProduct->execute([
                    ':product_id' => $product['product_id'],
                ]);
            }

            // Update the promo price in the orders table for the given phone number
            if (!$use_existing_promo) {
                $stmtUpdatePromoPrice = $pdo->prepare("
                    UPDATE order_items
                    SET promo_price = promo_price + :promo_price
                    WHERE phone_number = :phone_number OR phone_number = :phone_number
                    ORDER BY order_id DESC LIMIT 1
                ");
                $stmtUpdatePromoPrice->execute([
                    ':promo_price' => $existing_promo_price,
                    ':phone_number' => $phone_number,
                ]);
            }

            if (empty($error)) {
                $pdo->commit();
                $success = 'Order added successfully and stock updated!';
                header('Location: manage_orders.php');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['fetch_buying_price_code']) && isset($_GET['co_code'])) {
    $co_code = $_GET['co_code'];
    $stmt = $pdo->prepare("SELECT buying_price_code FROM product_codes WHERE co_code = :co_code");
    $stmt->execute([':co_code' => $co_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['success' => true, 'buying_price_code' => $result['buying_price_code']]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Order</title>
    <link rel="stylesheet" href="../assets/css/Add_Order.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Updated script imports for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.8/purify.min.js"></script>
</head>

<body>
    <div class="container">
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
            <div class="title">
                <h1 class="top-bar">Add New Order</h1>
                <div>
                    <a href="P_P.php"><i class="fas fa-key"></i> Enter Code</a>
                    <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                </div>
            </div>
            <div class="container2">
                <form method="POST" class="form-container" id="orderForm">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="delivery_method"><i class="fas fa-truck"></i> Delivery Method</label>
                        <select id="delivery_method" name="delivery_method" required>
                            <option value="Home" selected>Home</option>
                            <option value="Courier">Courier</option>
                        </select>
                    </div>

                    <div id="courier-fields" style="display: none;">
                        <div class="form-group">
                            <label for="tracking_number"><i class="fas fa-hashtag"></i> Tracking Number</label>
                            <input type="text" id="tracking_number" name="tracking_number">
                        </div>
                        <div class="form-group">
                            <label for="customer_name"><i class="fas fa-user"></i> Customer Name</label>
                            <input type="text" id="customer_name" name="customer_name">
                        </div>
                        <div class="form-group">
                            <label for="phone_number1"><i class="fas fa-phone"></i> Phone Number 1</label>
                            <input type="text" id="phone_number1" name="phone_number1">
                        </div>
                        <div class="form-group">
                            <label for="phone_number2"><i class="fas fa-phone"></i> Phone Number 2</label>
                            <input type="text" id="phone_number2" name="phone_number2">
                        </div>
                        <div class="form-group">
                            <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea id="address" name="address"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="district"><i class="fas fa-city"></i> District</label>
                            <input type="text" id="district" name="district">
                        </div>
                        <div class="form-group">
                            <label for="district"><i class="fas fa-city"></i> Payment Method</label>
                            <select id="delivery_method" name="payment" required>
                                <option value="CASH_ON_DELIVERY" selected>CASH_ON_DELIVERY</option>
                                <option value="BANK_TRANSFER">BANK_TRANSFER</option>
                        </select>
                        </div>
                    </div>

                    <div id="delivery_fee_field" style="display: none;">
                        <div class="form-group">
                            <label for="delivery_fee"><i class="fas fa-dollar-sign"></i> Delivery Fee</label>
                            <input type="number" id="delivery_fee" name="delivery_fee" step="0.01">
                        </div>
                    </div>

                    <div id="product-fields">
                        <div class="product-entry" data-index="0">
                            <div class="form-group">
                                <label for="origin_country"><i class="fas fa-globe"></i> Origin Country</label>
                                <select id="origin_country" name="products[0][origin_country]" onchange="updateCategories(this)" required>
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="category"><i class="fas fa-tags"></i> Category</label>
                                <select id="category" name="products[0][category]" onchange="updateProducts(this)" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="product_id"><i class="fas fa-box"></i> Product</label>
                                <select id="product_id" name="products[0][product_id]" onchange="updateSizes(this)" required>
                                    <option value="">Select Product</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="size"><i class="fas fa-ruler"></i> Size</label>
                                <select id="size" name="products[0][size]" required>
                                    <option value="">Select Size</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity"><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                                <input type="number" id="quantity" name="products[0][quantity]" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="co_code_0"><i class="fas fa-dollar-sign"></i> Co Code</label>
                                <input type="text" id="co_code_0" name="products[0][co_code]" required onblur="fetchBuyingPrice(this)">
                            </div>
                            <div class="form-group"  style="display: none;">
                                <label for="buying_price_code_0"><i class="fas fa-dollar-sign"></i> Buying Price (Code)</label>
                                <input type="text" id="buying_price_code_0" name="products[0][buying_price_code]" readonly>
                            </div>
                            <div class="form-group">
                                <label for="selling_price"><i class="fas fa-dollar-sign"></i> Selling Price</label>
                                <input type="number" id="selling_price" name="products[0][selling_price]" step="0.01" required oninput="handleSellingPriceInput(this)">
                            </div>
                            <div class="form-group">
                                <label for="discount"><i class="fas fa-percent"></i> Discount</label>
                                <input type="number" id="discount" name="products[0][discount]" step="0.01" oninput="handleDiscountInput(this)">
                            </div>
                            <div class="form-group">
                                <label for="promo_price"><i class="fas fa-dollar-sign"></i> Promo Price</label>
                                <input type="number" id="promo_price" name="products[0][promo_price]" step="0.01" readonly>
                            </div>
                            <div class="form-group">
                                <label for="final_price"><i class="fas fa-dollar-sign"></i> Final Price</label>
                                <input type="number" id="final_price" name="products[0][final_price]" step="0.01" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" id="phone_number" name="phone_number" required onblur="checkPhoneNumber()">
                    </div>

                    <div class="form-group">
                        <label for="use_existing_promo"><i class="fas fa-check"></i> Use Existing Promo Price</label>
                        <input type="checkbox" id="use_existing_promo" name="use_existing_promo" onchange="toggleExistingPromo()">
                    </div>

                    <div class="form-group" id="existing_promo_price_field" style="display: none;">
                        <label for="existing_promo_price"><i class="fas fa-dollar-sign"></i> Existing Promo Price</label>
                        <input type="number" id="existing_promo_price" name="existing_promo_price" step="0.01" readonly>
                    </div>

                    <button type="button" onclick="addProductField()" class="add-product-btn">
                        <i class="fas fa-plus"></i> Add Another Product
                    </button>

                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                        <select id="status" name="status">
                            <option value="Pending">Pending</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Returned">Returned</option>
                        </select>
                    </div>

                    <div id="return_reason_field" style="display: none;">
                        <div class="form-group">
                            <label for="return_reason"><i class="fas fa-undo"></i> Return Reason</label>
                            <textarea id="return_reason" name="return_reason"></textarea>
                        </div>
                    </div>

                    <button type="button" onclick="showOrderSummary()" class="submit-btn">
                        <i class="fas fa-plus"></i> Add Order
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Order Summary -->
    <div id="orderSummaryModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 style="text-align: center; margin-bottom: 20px;">Order Summary</h2>
            <div id="orderSummaryContent"></div>
            <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                <button onclick="closeModal()" style="background-color: #6c757d;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button onclick="downloadPDF()" style="background-color: #17a2b8;">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <button onclick="printPDF()" style="background-color: #ffc107;">
                    <i class="fas fa-print"></i> Print PDF
                </button>
                <button onclick="submitOrder()" style="background-color: #28a745;">
                    <i class="fas fa-check"></i> Confirm Order
                </button>
            </div>
        </div>
    </div>

    <script>
        // Delivery method toggle
        const deliveryMethod = document.getElementById('delivery_method');
        const courierFields = document.getElementById('courier-fields');
        const deliveryFeeField = document.getElementById('delivery_fee_field');

        deliveryMethod.addEventListener('change', function() {
            if (this.value === 'Courier') {
                courierFields.style.display = 'block';
                deliveryFeeField.style.display = 'block';
            } else {
                courierFields.style.display = 'none';
                deliveryFeeField.style.display = 'none';
            }
        });

        // Status change to show/hide return reason
        document.getElementById('status').addEventListener('change', function() {
            const returnReasonField = document.getElementById('return_reason_field');
            if (this.value === 'Returned') {
                returnReasonField.style.display = 'block';
                document.getElementById('return_reason').setAttribute('required', 'required');
            } else {
                returnReasonField.style.display = 'none';
                document.getElementById('return_reason').removeAttribute('required');
            }
        });

        function fetchBuyingPrice(coCodeInput) {
        const coCode = coCodeInput.value.trim();
        const productEntry = coCodeInput.closest('.product-entry');
        const index = productEntry.dataset.index || 0;
        const buyingPriceInput = document.getElementById('buying_price_code_' + index);

        if (!coCode) {
            buyingPriceInput.value = '';
            return;
        }

        fetch(`add_code.php?fetch_buying_price_code=1&co_code=${encodeURIComponent(coCode)}`)
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
        // Fetch buying price
        // function fetchBuyingPrice() {
        //     const code = document.getElementById('buying_price_code').value;
        //     if (code) {
        //         fetch(`fetch_buying_price.php?code=${code}`)
        //             .then(response => response.json())
        //             .then(data => {
        //                 if (data.success) {
        //                     document.getElementById('buying_price').value = data.buying_price;
        //                 } else {
        //                     alert('Invalid code!');
        //                 }
        //             });
        //     }
        // }

        // Update categories
        function updateCategories(originField) {
            const country = originField.value;
            const categoryField = originField.closest('.product-entry').querySelector('[name*="[category]"]');
            fetch(`get_categories.php?country=${country}`)
                .then(response => response.json())
                .then(data => {
                    categoryField.innerHTML = '<option value="">Select Category</option>';
                    if (data.categories) {
                        data.categories.forEach(item => {
                            categoryField.innerHTML += `<option value="${item}">${item}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching categories:', error);
                    // Fallback in case of API error
                    const fallbackCategories = {
                        'Vietnam': ['Clothing', 'Electronics', 'Accessories'],
                        'China': ['Electronics', 'Home Goods', 'Tools'],
                        'India': ['Textiles', 'Spices', 'Handicrafts'],
                        'Local': ['Food', 'Services', 'Crafts']
                    };

                    categoryField.innerHTML = '<option value="">Select Category</option>';
                    if (fallbackCategories[country]) {
                        fallbackCategories[country].forEach(category => {
                            categoryField.innerHTML += `<option value="${category}">${category}</option>`;
                        });
                    }
                });
        }

        // Update products
        function updateProducts(categoryField) {
            const countryField = categoryField.closest('.product-entry').querySelector('[name*="[origin_country]"]');
            const country = countryField.value;
            const category = categoryField.value;
            const productField = categoryField.closest('.product-entry').querySelector('[name*="[product_id]"]');
            fetch(`get_products.php?origin_country=${country}&category=${category}`)
                .then(response => response.json())
                .then(data => {
                    productField.innerHTML = '<option value="">Select Product</option>';
                    if (data.products) {
                        data.products.forEach(item => {
                            productField.innerHTML += `<option value="${item.product_id}">${item.name}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching products:', error);
                    // Fallback data
                    const fallbackProducts = {
                        'Clothing': [{
                                product_id: 101,
                                name: 'T-Shirt'
                            },
                            {
                                product_id: 102,
                                name: 'Jeans'
                            },
                            {
                                product_id: 103,
                                name: 'Dress'
                            }
                        ],
                        'Electronics': [{
                                product_id: 201,
                                name: 'Smartphone'
                            },
                            {
                                product_id: 202,
                                name: 'Laptop'
                            },
                            {
                                product_id: 203,
                                name: 'Headphones'
                            }
                        ],
                        'Accessories': [{
                                product_id: 301,
                                name: 'Watch'
                            },
                            {
                                product_id: 302,
                                name: 'Bag'
                            },
                            {
                                product_id: 303,
                                name: 'Wallet'
                            }
                        ]
                    };

                    productField.innerHTML = '<option value="">Select Product</option>';
                    if (fallbackProducts[category]) {
                        fallbackProducts[category].forEach(product => {
                            productField.innerHTML += `<option value="${product.product_id}">${product.name}</option>`;
                        });
                    }
                });
        }

        // Update sizes
        function updateSizes(productField) {
            const productId = productField.value;
            const sizeField = productField.closest('.product-entry').querySelector('[name*="[size]"]');
            fetch(`get_sizes.php?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    sizeField.innerHTML = '<option value="">Select Size</option>';
                    if (data.sizes) {
                        data.sizes.forEach(item => {
                            sizeField.innerHTML += `<option value="${item.size}">${item.size}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching sizes:', error);
                    // Fallback data
                    const fallbackSizes = {
                        101: [{
                            size: 'S'
                        }, {
                            size: 'M'
                        }, {
                            size: 'L'
                        }, {
                            size: 'XL'
                        }],
                        102: [{
                            size: '30'
                        }, {
                            size: '32'
                        }, {
                            size: '34'
                        }, {
                            size: '36'
                        }],
                        103: [{
                            size: 'S'
                        }, {
                            size: 'M'
                        }, {
                            size: 'L'
                        }],
                        201: [{
                            size: '64GB'
                        }, {
                            size: '128GB'
                        }, {
                            size: '256GB'
                        }],
                        202: [{
                            size: '13"'
                        }, {
                            size: '15"'
                        }, {
                            size: '17"'
                        }],
                        203: [{
                            size: 'Standard'
                        }],
                        301: [{
                            size: 'One Size'
                        }],
                        302: [{
                            size: 'Small'
                        }, {
                            size: 'Medium'
                        }, {
                            size: 'Large'
                        }],
                        303: [{
                            size: 'Standard'
                        }]
                    };

                    sizeField.innerHTML = '<option value="">Select Size</option>';
                    if (fallbackSizes[productId]) {
                        fallbackSizes[productId].forEach(size => {
                            sizeField.innerHTML += `<option value="${size.size}">${size.size}</option>`;
                        });
                    }
                });
        }

        // Add new product field
        function addProductField() {
            const container = document.getElementById('product-fields');
            const index = container.children.length;
            const entry = `
                <div class="product-entry" data-index="${index}">
                    <h3>Product #${index + 1}</h3>
                    <div class="form-group">
                        <label for="origin_country_${index}"><i class="fas fa-globe"></i> Origin Country</label>
                        <select id="origin_country_${index}" name="products[${index}][origin_country]" onchange="updateCategories(this)" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category_${index}"><i class="fas fa-tags"></i> Category</label>
                        <select id="category_${index}" name="products[${index}][category]" onchange="updateProducts(this)" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="product_id_${index}"><i class="fas fa-box"></i> Product</label>
                        <select id="product_id_${index}" name="products[${index}][product_id]" onchange="updateSizes(this)" required>
                            <option value="">Select Product</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="size_${index}"><i class="fas fa-ruler"></i> Size</label>
                        <select id="size_${index}" name="products[${index}][size]" required>
                            <option value="">Select Size</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity_${index}"><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                        <input type="number" id="quantity_${index}" name="products[${index}][quantity]" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="buying_price_code_${index}"><i class="fas fa-dollar-sign"></i> Buying Price (Code)</label>
                        <input type="text" id="buying_price_code_${index}" name="products[${index}][buying_price_code]" onchange="fetchBuyingPrice()" required>
                    </div>
                    <div class="form-group">
                        <label for="selling_price_${index}"><i class="fas fa-dollar-sign"></i> Selling Price</label>
                        <input type="number" id="selling_price_${index}" name="products[${index}][selling_price]" step="0.01" required oninput="handleSellingPriceInput(this)">
                    </div>
                    <div class="form-group">
                        <label for="discount_${index}"><i class="fas fa-percent"></i> Discount</label>
                        <input type="number" id="discount_${index}" name="products[${index}][discount]" step="0.01" oninput="handleDiscountInput(this)">
                    </div>
                    <div class="form-group">
                        <label for="promo_price_${index}"><i class="fas fa-dollar-sign"></i> Promo Price</label>
                        <input type="number" id="promo_price_${index}" name="products[${index}][promo_price]" step="0.01" readonly>
                    </div>
                    <div class="form-group">
                        <label for="final_price_${index}"><i class="fas fa-dollar-sign"></i> Final Price</label>
                        <input type="number" id="final_price_${index}" name="products[${index}][final_price]" step="0.01" readonly>
                    </div>
                    <button type="button" class="remove-product-btn" onclick="removeProductField(this)">
                        <i class="fas fa-trash"></i> Remove Product
                    </button>
                </div>`;
            container.insertAdjacentHTML('beforeend', entry);
        }

        // Remove product field
        function removeProductField(button) {
            const productEntry = button.closest('.product-entry');
            productEntry.remove();

            // Renumber the remaining products for visual clarity
            const productEntries = document.querySelectorAll('.product-entry');
            productEntries.forEach((entry, index) => {
                const heading = entry.querySelector('h3');
                if (heading) {
                    heading.textContent = `Product #${index + 1}`;
                }
            });
        }

        let sellingPrices = {};

        // Handle selling price input
        function handleSellingPriceInput(sellingPriceField) {
            const productEntry = sellingPriceField.closest('.product-entry');
            const index = productEntry.dataset.index;
            const sellingPrice = parseFloat(sellingPriceField.value) || 0;
            sellingPrices[index] = sellingPrice;
            calculatePromoPrice(productEntry);
        }

        // Handle discount input
        function handleDiscountInput(discountField) {
            const productEntry = discountField.closest('.product-entry');
            calculatePromoPrice(productEntry);
        }

        // Calculate promo price
        function calculatePromoPrice(productEntry) {
            const index = productEntry.dataset.index;
            const sellingPrice = sellingPrices[index] || 0;
            const discountField = productEntry.querySelector('[name*="[discount]"]');
            const discount = parseFloat(discountField.value) || 0;
            const quantity = parseInt(productEntry.querySelector('[name*="[quantity]"]').value) || 1;
            const promoPriceField = productEntry.querySelector('[name*="[promo_price]"]');
            const useExistingPromo = document.getElementById('use_existing_promo').checked;
            const existingPromoPriceField = document.getElementById('existing_promo_price');
            const deliveryFee = parseFloat(document.getElementById('delivery_fee')?.value || '0.00');

            // If you want to split delivery fee equally among products:
            const productEntries = document.querySelectorAll('.product-entry');
            const deliveryFeePerProduct = productEntries.length > 0 ? deliveryFee / productEntries.length : 0;

            if (!isNaN(sellingPrice)) {
                const priceAfterDiscount = (sellingPrice * quantity) - discount;
                const promoPrice = priceAfterDiscount * 0.002; // 0.2% of price after discount
                promoPriceField.value = promoPrice.toFixed(2);

                let finalPrice;
                if (useExistingPromo) {
                    const existingPromoPrice = parseFloat(existingPromoPriceField.value) || 0;
                    finalPrice = priceAfterDiscount - existingPromoPrice + deliveryFeePerProduct;
                } else {
                    finalPrice = priceAfterDiscount + deliveryFeePerProduct;
                }

                const finalPriceField = productEntry.querySelector('[name*="[final_price]"]');
                finalPriceField.value = finalPrice.toFixed(2);
            } else {
                promoPriceField.value = '';
            }
        }

        // Toggle existing promo price
        function toggleExistingPromo() {
            const useExistingPromo = document.getElementById('use_existing_promo').checked;
            const existingPromoPriceField = document.getElementById('existing_promo_price_field');
            existingPromoPriceField.style.display = useExistingPromo ? 'block' : 'none';

            // Recalculate the final price when the checkbox state changes
            const productEntries = document.querySelectorAll('.product-entry');
            productEntries.forEach(productEntry => {
                calculatePromoPrice(productEntry);
            });
        }

        // Check phone number for existing promo
        function checkPhoneNumber() {
            const phoneNumber = document.getElementById('phone_number').value;
            if (phoneNumber) {
                fetch(`check_phone_number.php?phone_number=${phoneNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            document.getElementById('existing_promo_price').value = data.promo_price;
                            // Recalculate prices
                            const productEntries = document.querySelectorAll('.product-entry');
                            productEntries.forEach(productEntry => {
                                calculatePromoPrice(productEntry);
                            });
                        } else {
                            document.getElementById('existing_promo_price').value = '0.00';
                            alert('No promo price available yet');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking phone number:', error);
                        document.getElementById('existing_promo_price').value = '0.00';
                    });
            }
        }

        // Show order summary
        function showOrderSummary() {
            // Basic form validation
            const form = document.getElementById('orderForm');
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error-field');
                    isValid = false;
                } else {
                    field.classList.remove('error-field');
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields');
                return;
            }

            const deliveryMethod = document.getElementById('delivery_method').value;
            const customerName = document.getElementById('customer_name')?.value || 'N/A';
            const phoneNumber = document.getElementById('phone_number').value;
            const address = document.getElementById('address')?.value || 'N/A';
            const district = document.getElementById('district')?.value || 'N/A';
            const deliveryFee = parseFloat(document.getElementById('delivery_fee')?.value || '0.00');
            const status = document.getElementById('status').value;

            const productEntries = document.querySelectorAll('.product-entry');
            let productsSummary = '<table style="width:100%; border-collapse: collapse; margin: 15px 0;">';
            productsSummary += '<tr style="background-color: #f2f2f2; border-bottom: 1px solid #ddd;">' +
                '<th style="padding: 8px; text-align: left; border-right: 1px solid #ddd;">Product</th>' +
                '<th style="padding: 8px; text-align: center; border-right: 1px solid #ddd;">Size</th>' +
                '<th style="padding: 8px; text-align: center; border-right: 1px solid #ddd;">Qty</th>' +
                '<th style="padding: 8px; text-align: right; border-right: 1px solid #ddd;">Price</th>' +
                '<th style="padding: 8px; text-align: right;">Total</th>' +
                '</tr>';

            let itemCount = 0;
            let totalDiscount = 0;
            let subtotal = 0;

            productEntries.forEach((entry, index) => {
                const productSelect = entry.querySelector('[name*="[product_id]"]');
                const productName = productSelect.options[productSelect.selectedIndex]?.textContent?.trim() || 'N/A';

                const sizeSelect = entry.querySelector('[name*="[size]"]');
                const size = sizeSelect.options[sizeSelect.selectedIndex]?.textContent?.trim() || 'N/A';

                const quantity = parseInt(entry.querySelector('[name*="[quantity]"]').value || '0');
                const sellingPrice = parseFloat(entry.querySelector('[name*="[selling_price]"]').value || '0.00');
                const discount = parseFloat(entry.querySelector('[name*="[discount]"]').value || '0.00');
                const finalPrice = parseFloat(entry.querySelector('[name*="[final_price]"]').value || '0.00');
                const lineTotal = sellingPrice * quantity;

                itemCount += quantity;
                totalDiscount += discount;
                subtotal += lineTotal;

                productsSummary += '<tr style="border-bottom: 1px solid #ddd;">' +
                    `<td style="padding: 8px; text-align: left; border-right: 1px solid #ddd;">${productName}</td>` +
                    `<td style="padding: 8px; text-align: center; border-right: 1px solid #ddd;">${size}</td>` +
                    `<td style="padding: 8px; text-align: center; border-right: 1px solid #ddd;">${quantity}</td>` +
                    `<td style="padding: 8px; text-align: right; border-right: 1px solid #ddd;">${sellingPrice.toFixed(2)}</td>` +
                    `<td style="padding: 8px; text-align: right;">${lineTotal.toFixed(2)}</td>` +
                    '</tr>';
            });

            const useExistingPromo = document.getElementById('use_existing_promo').checked;
            const existingPromoPrice = parseFloat(document.getElementById('existing_promo_price').value) || 0;

            let totalPrice;
            if (useExistingPromo) {
                totalPrice = subtotal + deliveryFee - totalDiscount - existingPromoPrice;
            } else {
                totalPrice = subtotal + deliveryFee - totalDiscount;
            }

            // Add summary rows
            productsSummary += '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td colspan="4" style="padding: 8px; text-align: right; font-weight: bold;">Subtotal:</td>' +
                `<td style="padding: 8px; text-align: right;">${subtotal.toFixed(2)}</td>` +
                '</tr>';

            productsSummary += '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td colspan="4" style="padding: 8px; text-align: right; font-weight: bold;">Delivery Fee:</td>' +
                `<td style="padding: 8px; text-align: right;">${deliveryFee.toFixed(2)}</td>` +
                '</tr>';

            productsSummary += '<tr style="border-bottom: 1px solid #ddd;">' +
                '<td colspan="4" style="padding: 8px; text-align: right; font-weight: bold;">Total Discount:</td>' +
                `<td style="padding: 8px; text-align: right;">${totalDiscount.toFixed(2)}</td>` +
                '</tr>';

            if (useExistingPromo) {
                productsSummary += '<tr>' +
                    '<td colspan="4" style="padding: 8px; text-align: right; font-weight: bold;">Promo Price:</td>' +
                    `<td style="padding: 8px; text-align: right;">-${existingPromoPrice.toFixed(2)}</td>` +
                    '</tr>';
            }

            productsSummary += '<tr>' +
                '<td colspan="4" style="padding: 8px; text-align: right; font-weight: bold; font-size: 16px;">TOTAL:</td>' +
                `<td style="padding: 8px; text-align: right; font-weight: bold; font-size: 16px;">${totalPrice.toFixed(2)}</td>` +
                '</tr>';

            productsSummary += '</table>';

            const receiptNumber = 'REC-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
            const orderDateTime = new Date().toLocaleString();

            const summaryContent = `
                <div class="pdf-content">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px;">
                        <h2 style="margin: 5px 0;">ORDER RECEIPT</h2>
                        <p style="margin: 5px 0; font-size: 14px;">${orderDateTime}</p>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <p><strong>Receipt Number:</strong> ${receiptNumber}</p>
                            <p><strong>Order Status:</strong> ${status}</p>
                        </div>
                        <div>
                            <p><strong>Delivery Method:</strong> ${deliveryMethod}</p>
                            <p><strong>Delivery Fee:</strong> ${deliveryFee.toFixed(2)}</p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border-radius: 5px;">
                        <h3 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Customer Information</h3>
                        <p><strong>Name:</strong> ${customerName}</p>
                        <p><strong>Phone:</strong> ${phoneNumber}</p>
                        <p><strong>Address:</strong> ${address}${district ? ', ' + district : ''}</p>
                    </div>
                    
                    <h3 style="margin-bottom: 10px;">Order Items (${itemCount} items)</h3>
                    ${productsSummary}
                    
                    <div style="text-align: center; margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd;">
                        <h3>Thank You For Your Order!</h3>
                    </div>
                </div>
            `;

            document.getElementById('orderSummaryContent').innerHTML = summaryContent;
            document.getElementById('orderSummaryModal').style.display = 'block';
        }

        // Close modal
        function closeModal() {
            document.getElementById('orderSummaryModal').style.display = 'none';
        }

        // Submit order
        function submitOrder() {
            document.getElementById('orderForm').submit();
        }

        // Download PDF
        function downloadPDF() {
            // Create a temporary div to hold cleaned content for PDF conversion
            const tempDiv = document.createElement('div');
            tempDiv.className = 'pdf-content';

            // Get and sanitize the order summary content
            const summaryContent = document.getElementById('orderSummaryContent').innerHTML;
            const cleanContent = DOMPurify.sanitize(summaryContent);
            tempDiv.innerHTML = cleanContent;

            // Apply PDF-friendly styling
            tempDiv.style.padding = '20px';
            tempDiv.style.color = '#000';
            tempDiv.style.backgroundColor = '#fff';
            tempDiv.style.fontFamily = 'Arial, sans-serif';
            tempDiv.style.width = '580px'; // Fixed width for PDF

            // Temporarily add to document for rendering
            tempDiv.style.position = 'absolute';
            tempDiv.style.left = '-9999px';
            document.body.appendChild(tempDiv);

            // Use html2canvas to capture the content as an image
            html2canvas(tempDiv, {
                scale: 1.5, // Higher scale for better quality
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                // Remove the temporary element
                document.body.removeChild(tempDiv);

                // Create PDF
                const {
                    jsPDF
                } = window.jspdf;
                const pdf = new jsPDF('p', 'pt', 'a4');

                // Get dimensions
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = 510; // A4 width in pts at 72 DPI
                const pageHeight = 842; // A4 height in pts at 72 DPI
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 20; // Initial position from top

                // Add image to first page
                pdf.addImage(imgData, 'PNG', 40, position, imgWidth, imgHeight);
                heightLeft -= pageHeight - 40;

                // Add new pages if needed for long content
                while (heightLeft > 0) {
                    position = 0;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 40, position - (pageHeight - 40), imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Add company info at the bottom of the first page
                pdf.setFontSize(10);
                pdf.setTextColor(100, 100, 100);
                const footer = "Thank you for your order!";
                const textWidth = pdf.getStringUnitWidth(footer) * 10 / pdf.internal.scaleFactor;
                const textX = (pdf.internal.pageSize.width - textWidth) / 2;
                pdf.text(footer, textX, pageHeight - 20);

                // Save the PDF
                pdf.save('order_summary.pdf');
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('There was an error generating the PDF. Please try again.');
            });
        }

        // Print PDF
        function printPDF() {
            const printContent = document.getElementById('orderSummaryContent').innerHTML;

            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            printWindow.document.open();
            printWindow.document.write(`
                <html>
                <head>
                    <title>Order Summary</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                        }
                        h2 {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 15px 0;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 8px;
                            text-align: left;
                        }
                        th {
                            background-color: #f2f2f2;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 30px;
                            font-size: 14px;
                            color: #555;
                        }
                    </style>
                </head>
                <body>
                    <h2>Order Summary</h2>
                    ${printContent}
                    <div class="footer">
                        Thank you for your order!
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }

        // Add event listeners when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add the first product entry heading
            const firstProductEntry = document.querySelector('.product-entry');
            if (firstProductEntry) {
                const heading = document.createElement('h3');
                heading.textContent = 'Product #1';
                firstProductEntry.insertBefore(heading, firstProductEntry.firstChild);
            }

            // If status is changed to 'Returned', show return reason field
            document.getElementById('status').dispatchEvent(new Event('change'));

            // Add custom CSS for validation
            const style = document.createElement('style');
            style.textContent = `
                .error-field {
                    border: 2px solid #f44336 !important;
                    background-color: #fff8f8;
                }
                
                .product-entry h3 {
                    margin-top: 0;
                    margin-bottom: 15px;
                    color: #007bff;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 5px;
                }
                
                .remove-product-btn {
                    background-color: #dc3545;
                    color: white;
                    margin-top: 10px;
                }
                
                .remove-product-btn:hover {
                    background-color: #c82333;
                }
                
                .add-product-btn {
                    background-color: #17a2b8;
                    color: white;
                    margin: 15px 0;
                }
                
                .add-product-btn:hover {
                    background-color: #138496;
                }
                
                .submit-btn {
                    background-color: #28a745;
                    color: white;
                    font-size: 16px;
                    padding: 10px 15px;
                    margin-top: 20px;
                }
                
                .submit-btn:hover {
                    background-color: #218838;
                }
                
                @media print {
                    body * {
                        visibility: hidden;
                    }
                    
                    .modal-content, .modal-content * {
                        visibility: visible;
                    }
                    
                    .modal-content {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        margin: 0;
                        padding: 15px;
                    }
                    
                    button {
                        display: none;
                    }
                }
                
                .pdf-content h3 {
                    margin: 12px 0;
                    font-size: 16px;
                }
                
                .pdf-content p {
                    margin: 8px 0;
                    font-size: 12px;
                    line-height: 1.5;
                }
                
                .pdf-content ul {
                    padding-left: 20px;
                    margin: 10px 0;
                }
                
                .pdf-content li {
                    margin-bottom: 5px;
                    font-size: 12px;
                }
                
                /* Modal Styles */
                .modal {
                    display: none;
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0, 0, 0, 0.4);
                }
                
                .modal-content {
                    background-color: #fefefe;
                    margin: 5% auto;
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%;
                    max-width: 900px;
                    border-radius: 8px;
                    max-height: 90vh;
                    overflow-y: auto;
                }
                
                .close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                
                .close:hover,
                .close:focus {
                    color: black;
                    text-decoration: none;
                    cursor: pointer;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>

</html>