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
            o.phone_number,
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
        $query .= " AND (o.tracking_number LIKE :search OR o.phone_number LIKE :search OR o.address LIKE :search OR p.name LIKE :search OR o.customer_name LIKE :search)";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        .title {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
            margin-bottom: 2rem;
            font-weight: 700;
            color: #212529;
        }
        .title a {
            margin-left: auto;
            font-size: 1rem;
        }
        .card {
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .filter-form label {
            font-weight: 600;
            color: #495057;
            margin-right: 0.5rem;
        }
        .filter-form input[type="text"], .filter-form select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #fff;
            padding: 0.4rem 1rem;
            font-size: 1rem;
            min-width: 180px;
        }
        .filter-form button {
            border-radius: 0.5rem;
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: #fff;
            border: none;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .filter-form button:hover {
            background: linear-gradient(90deg, #0056b3 0%, #007bff 100%);
        }
        @media (max-width: 991px) {
            .filter-form { flex-direction: column; align-items: flex-start; gap: 0.7rem; }
        }
        .table-container {
            overflow-x: auto;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        }
        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
        }
        thead {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: #fff;
        }
        th, td {
            padding: 0.85rem 0.5rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            font-size: 0.97rem;
        }
        th {
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        tbody tr:hover {
            background: #f1f3f5;
            transition: background 0.2s;
        }
        img {
            border-radius: 0.5rem;
            margin-bottom: 2px;
            border: 1px solid #e9ecef;
        }
        .actions a, .actions button {
            margin-bottom: 4px;
            display: inline-block;
        }
        .actions form {
            display: inline;
        }
        .badge-status {
            font-size: 0.95em;
            padding: 0.4em 0.8em;
            border-radius: 0.5em;
            display: inline-block;
            font-weight: 600;
            letter-spacing: 0.02em;
            /* fallback for unknown statuses */
            background: #e0e0e0;
            color: #333;
        }
        .badge-status.Pending, .badge-status.PENDING, .badge-status.pending {
            background: #ffe082 !important;
            color: #795548 !important;
        }
        .badge-status.Shipped, .badge-status.shipped {
            background: #81d4fa !important;
            color: #01579b !important;
        }
        .badge-status.Delivered, .badge-status.delivered {
            background: #a5d6a7 !important;
            color: #1b5e20 !important;
        }
        .badge-status.Returned, .badge-status.returned {
            background: #ef9a9a !important;
            color: #b71c1c !important;
        }
        .badge-status.Cancelled, .badge-status.cancelled {
            background: #e57373 !important;
            color: #c62828 !important;
        }
        .table th, .table td { vertical-align: middle; }
        .table thead th { border-top: none; }
        .table td .badge { font-size: 0.95em; }
        .btn-outline-primary, .btn-outline-danger {
            border-radius: 0.5rem;
        }
        .btn-outline-primary:hover {
            background: #007bff;
            color: #fff;
        }
        .btn-outline-danger:hover {
            background: #dc3545;
            color: #fff;
        }
        @media (max-width: 1200px) {
            table { min-width: 900px; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 1rem; }
            .title { font-size: 1.2rem; }
        }
    </style>
    <script>
        function fetchOrders() {
            let searchKeyword = $("#search").val();
            $.ajax({
                url: "fetch_orders.php",
                method: "POST",
                data: { search: searchKeyword },
                success: function(response) {
                    $("tbody").html(response);
                }
            });
        }
    </script>
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
        <main class="main-content">
            <div class="title">
                <i class="fas fa-cogs"></i> Manage Orders
                <a href="add_order.php" class="btn btn-success btn-sm"><i class="fas fa-plus-circle"></i> Add Order</a>
            </div>
            <div class="card p-4">
                <form method="GET" class="filter-form mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="search" class="form-label mb-0"><i class="fas fa-search"></i></label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Search by tracking number, customer, product..." value="<?= htmlspecialchars($search_keyword ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="status" class="form-label mb-0">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All</option>
                            <option value="PENDING" <?= $filter_status == 'PENDING' ? 'selected' : '' ?>>Pending</option>
                            <option value="Shipped" <?= $filter_status == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="Delivered" <?= $filter_status == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="Returned" <?= $filter_status == 'Returned' ? 'selected' : '' ?>>Returned</option>
                            <option value="Cancelled" <?= $filter_status == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit">Filter</button>
                </form>
                <div class="table-container">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Order ID</th>
                                <th><i class="fas fa-user"></i> Customer</th>
                                <th><i class="fas fa-phone"></i> Phone Number</th>
                                <th><i class="fas fa-box"></i> Product</th>
                                <th><i class="fas fa-tags"></i> Category</th>
                                <th><i class="fas fa-barcode"></i> Tracking No</th>
                                <th><i class="fas fa-image"></i> Image</th>
                                <th><i class="fas fa-ruler"></i> Size</th>
                                <th><i class="fas fa-archive"></i> Qty</th>
                                <th><i class="fas fa-dollar-sign"></i> Co Code</th>
                                <th><i class="fas fa-dollar-sign"></i> Price</th>
                                <th><i class="fas fa-percent"></i> Discount</th>
                                <th style="min-width:220px;"><i class="fas fa-map-marker-alt"></i> Address</th>
                                <th><i class="fas fa-phone"></i> Phone 1</th>
                                <th><i class="fas fa-phone"></i> Phone 2</th>
                                <th><i class="fas fa-map-pin"></i> District</th>
                                <th><i class="fas fa-truck"></i> Delivery</th>
                                <th><i class="fas fa-dollar-sign"></i> Fee</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-info-circle"></i> Payment</th>
                                <th><i class="fas fa-undo"></i> Return Reason</th>
                                <th><i class="fas fa-calendar-alt"></i> Created</th>
                                <th><i class="fas fa-tools"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($order['phone_number']) ?></td>
                                        <td><?= $order['product_names'] ?></td>
                                        <td><?= $order['categories'] ?></td>
                                        <td><?= htmlspecialchars($order['tracking_number']) ?></td>
                                        <td>
                                            <?php
                                            $images = explode('<br>', $order['product_images']);
                                            foreach ($images as $image) {
                                                echo "<img src='" . htmlspecialchars($image) . "' style='width: 48px; height: 48px; object-fit: cover;'><br>";
                                            }
                                            ?>
                                        </td>
                                        <td><?= $order['sizes'] ?></td>
                                        <td><?= $order['quantities'] ?></td>
                                        <td><?= $order['co_codes'] ?></td>
                                        <td><?= $order['selling_prices'] ?></td>
                                        <td><?= $order['discounts'] ?></td>
                                        <td style="min-width:220px;"><?= htmlspecialchars($order['address']) ?></td>
                                        <td><?= htmlspecialchars($order['phone_number1']) ?></td>
                                        <td><?= htmlspecialchars($order['phone_number2']) ?></td>
                                        <td><?= htmlspecialchars($order['district']) ?></td>
                                        <td><?= htmlspecialchars($order['delivery_method']) ?></td>
                                        <td><?= htmlspecialchars($order['delivery_fee']) ?></td>
                                        <td>
                                            <?php
                                            // Always display the status as in the database, with a badge
                                            $status = htmlspecialchars($order['status']);
                                            $badgeClass = 'badge-status ' . preg_replace('/\s+/', '', $status);
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= $status ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($order['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($order['return_reason'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($order['created_at']) ?></td>
                                        <td class="actions">
                                            <a href="edit_order.php?order_id=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-primary mb-1"><i class="fas fa-edit"></i> Edit</a>
                                            <form action="delete_order.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this order?');">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="22" class="text-center text-muted">No orders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>