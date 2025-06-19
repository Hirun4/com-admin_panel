<?php
session_start();

if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found.");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$success = '';
try {
    $filter_status = $_GET['status'] ?? '';
    $search_keyword = $_GET['search'] ?? '';

    $query = "
        SELECT 
            o.order_id,
            o.tracking_number,
            o.customer_name,
            o.delivery_method,
            o.address,
            o.phone_number1,
            o.phone_number2,
            o.district,
            o.status,
            o.return_reason,
            o.created_at,
            o.delivery_fee,
            o.payment_method,
            GROUP_CONCAT(p.name SEPARATOR '<br>') AS product_names,
            GROUP_CONCAT(p.category SEPARATOR '<br>') AS categories,
            GROUP_CONCAT(oi.size SEPARATOR '<br>') AS sizes,
            GROUP_CONCAT(oi.quantity SEPARATOR '<br>') AS quantities,
            GROUP_CONCAT(oi.co_code SEPARATOR '<br>') AS co_codes,
            GROUP_CONCAT(oi.selling_price SEPARATOR '<br>') AS selling_prices,
            GROUP_CONCAT(oi.discount SEPARATOR '<br>') AS discounts,
            GROUP_CONCAT(p.image_url SEPARATOR '<br>') AS product_images
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE 1
    ";

    if ($filter_status) {
        $query .= " AND o.status = :status";
    }
    if ($search_keyword) {
        $query .= " AND (o.tracking_number LIKE :search OR o.address LIKE :search OR p.name LIKE :search OR o.customer_name LIKE :search)";
    }

    $query .= " GROUP BY o.order_id ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($query);

    if ($filter_status) {
        $stmt->bindParam(':status', $filter_status);
    }
    if ($search_keyword) {
        $search_term = "%" . $search_keyword . "%";
        $stmt->bindParam(':search', $search_term);
    }

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../assets/css/order.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function fetchOrders() {
            let searchKeyword = $("#search").val();

            $.ajax({
                url: "fetch_orders.php", // New PHP file to handle AJAX request
                method: "POST",
                data: {
                    search: searchKeyword
                },
                success: function(response) {
                    $("tbody").html(response); // Update the table body with filtered data
                }
            });
        }
    </script>
    <style>
        /* Highlighted horizontal scrollbar */
        .table-container {
            overflow-x: scroll;
            overflow-y: auto;
            scrollbar-width: auto; /* Always show in Firefox */
        }

        /* For Webkit browsers (Chrome, Safari, Edge) */
        .table-container::-webkit-scrollbar {
            height: 12px;
            background: #e0e0e0;
            display: block; /* Always show */
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #007bff;  /* Thumb color (highlight) */
            border-radius: 6px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #0056b3;  /* Thumb color on hover */
        }
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="../dashboard/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>
                <a href="../orders/manage_orders.php" class="active"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="title">
                <h1><i class="fas fa-cogs"></i> Manage Orders</h1>
                <a href="add_order.php"><i class="fas fa-plus-circle"></i> Add Order</a>
            </div>
            <div class="content">
                <form method="GET" class="filter-form">
                    <div>
                        <label for="search"><i class="fas fa-search"></i></label>
                        <input type="text" name="search" id="search" placeholder="Search by tracking number... " onkeyup="fetchOrders()" autocomplete="off">
                    </div>
                    <!-- <button type="submit"><i class="fas fa-search"></i>Search</button> -->
                     <div>
                         <label for="status"><i class="fas fa-filter"></i> Status:</label>
                         <select name="status" id="status">
                             <option value="">All</option>
                            <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Shipped" <?= $filter_status == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="Delivered" <?= $filter_status == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="Returned" <?= $filter_status == 'Returned' ? 'selected' : '' ?>>Returned</option>
                        </select>
                        <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                     </div>
                </form>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Order ID</th>
                                <th><i class="fas fa-user"></i> Customer Name</th>
                                <th><i class="fas fa-box"></i> Product</th>
                                <th><i class="fas fa-tags"></i> Category</th>
                                <th><i class="fas fa-barcode"></i> Tracking No</th>
                                <th><i class="fas fa-image"></i> Image</th>
                                <th><i class="fas fa-ruler"></i> Size</th>
                                <th><i class="fas fa-archive"></i> Quantity</th>
                                <th><i class="fas fa-dollar-sign"></i> Co Code</th>
                                <th><i class="fas fa-dollar-sign"></i> Selling Price</th>
                                <th><i class="fas fa-percent"></i> Discount</th>
                                <th><i class="fas fa-map-marker-alt"></i> Address</th>
                                <th><i class="fas fa-phone"></i> Phone Number 1</th>
                                <th><i class="fas fa-phone"></i> Phone Number 2</th>
                                <th><i class="fas fa-map-pin"></i> District</th>
                                <th><i class="fas fa-truck"></i> Delivery Method</th>
                                <th><i class="fas fa-dollar-sign"></i> Delivery Fee</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-info-circle"></i> Payment Method</th>
                                <th><i class="fas fa-undo"></i> Return Reason</th>
                                <th><i class="fas fa-calendar-alt"></i> Created At</th>
                                <th><i class="fas fa-tools"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                        <td><?= $order['product_names'] ?></td>
                                        <td><?= $order['categories'] ?></td> <!-- Display Category -->
                                        <td><?= htmlspecialchars($order['tracking_number']) ?></td>
                                        <td>
                                            <?php
                                            $images = explode('<br>', $order['product_images']);
                                            foreach ($images as $image) {
                                                echo "<img src='" . htmlspecialchars($image) . "' style='width: 50px; height: 50px; object-fit: cover;'><br>";
                                            }
                                            ?>
                                        </td>
                                        <td><?= $order['sizes'] ?></td>
                                        <td><?= $order['quantities'] ?></td>
                                        <td><?= $order['co_codes'] ?></td>
                                        <td><?= $order['selling_prices'] ?></td>
                                        <td><?= $order['discounts'] ?></td>
                                        <td><?= htmlspecialchars($order['address']) ?></td>
                                        <td><?= htmlspecialchars($order['phone_number1']) ?></td>
                                        <td><?= htmlspecialchars($order['phone_number2']) ?></td>
                                        <td><?= htmlspecialchars($order['district']) ?></td>
                                        <td><?= htmlspecialchars($order['delivery_method']) ?></td>
                                        <td><?= htmlspecialchars($order['delivery_fee']) ?></td>
                                        <td><?= htmlspecialchars($order['status']) ?></td>
                                        <td><?= htmlspecialchars($order['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($order['return_reason'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($order['created_at']) ?></td>
                                        <td>
                                            <a href="edit_order.php?order_id=<?= $order['order_id'] ?>"><i class="fas fa-edit"></i> Edit</a><br>
                                            <form action="delete_order.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this order?');">
        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
        <button type="submit" style="background:none;border:none;color:#c00;cursor:pointer;">
            <i class="fas fa-trash-alt"></i> Delete
        </button>
    </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="21">No orders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>