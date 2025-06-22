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

try {
    // Recalculate and update stock_quantity and stock_status for each product
    $updateStockQuery = "
        UPDATE products p
        LEFT JOIN (
            SELECT product_id, COALESCE(SUM(quantity), 0) AS total_stock
            FROM product_stock
            GROUP BY product_id
        ) ps ON p.product_id = ps.product_id
        SET 
            p.stock_quantity = ps.total_stock,
            p.stock_status = CASE 
                                WHEN ps.total_stock > 0 THEN 'In Stock' 
                                ELSE 'Out of Stock' 
                             END
    ";
    $pdo->exec($updateStockQuery);

    // Fetch products and stock details
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.category,
            p.origin_country,
            p.stock_quantity,
            p.stock_status,
            p.image_url,
            GROUP_CONCAT(CONCAT(ps.size, ':', ps.quantity) SEPARATOR ', ') AS stock_details
        FROM 
            products p
        LEFT JOIN 
            product_stock ps ON p.product_id = ps.product_id
        GROUP BY 
            p.product_id
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6fb;
        }

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

        .sidebar nav a.active,
        .sidebar nav a:hover {
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
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
        }

        .table-container {
            overflow-x: auto;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
        }

        thead {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: #fff;
        }

        th,
        td {
            padding: 0.85rem 0.5rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
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
            max-width: 60px;
            max-height: 60px;
        }

        .actions a {
            margin-right: 0.5rem;
        }

        .btn-outline-primary,
        .btn-outline-danger {
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
            table {
                min-width: 700px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                min-width: 100px;
            }

            .main-content {
                padding: 1rem;
            }

            .title {
                font-size: 1.2rem;
            }
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
            <div class="title">
                <i class="fas fa-boxes"></i> Manage Products
                <a href="add_product.php" class="btn btn-success btn-sm ms-auto"><i class="fas fa-plus-circle"></i> Add Product</a>
            </div>
            <div class="card p-4 table-container">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th><i class="fas fa-box"></i> Name</th>
                            <th><i class="fas fa-tag"></i> Price (Rs.)</th>
                            <th><i class="fas fa-warehouse"></i> Stock (Size:Quantity)</th>
                            <th><i class="fas fa-layer-group"></i> Total Stock</th>
                            <th><i class="fas fa-check-circle"></i> Stock Status</th>
                            <th><i class="fas fa-list"></i> Category</th>
                            <th><i class="fas fa-flag"></i> Origin Country</th>
                            <th><i class="fas fa-image"></i> Image</th>
                            <th><i class="fas fa-tools"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td>Rs. <?= number_format($product['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($product['stock_details'] ?: 'No Stock') ?></td>
                                    <td><?= htmlspecialchars($product['stock_quantity']) ?></td>
                                    <td><?= htmlspecialchars($product['stock_status']) ?></td>
                                    <td><?= htmlspecialchars($product['category']) ?></td>
                                    <td><?= htmlspecialchars($product['origin_country']) ?></td>
                                    <td>
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="Product Image">
                                        <?php else: ?>
                                            No Image
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a class="edit" href="edit_product.php?product_id=<?= $product['product_id'] ?>"><i class="fas fa-edit"></i> Edit</a>
                                        <a class="delete" href="delete_product.php?product_id=<?= $product['product_id'] ?>" onclick="return confirm('Are you sure?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>