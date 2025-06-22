<?php
session_start();
include_once '../config/config.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Handle Restock Action
if (isset($_GET['restore_stock_id'])) {
    $stock_id = intval($_GET['restore_stock_id']);

    try {
        $stmt = $pdo->prepare("UPDATE stock SET deleted_at = NULL WHERE id = :stock_id");
        $stmt->bindParam(':stock_id', $stock_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['message'] = "Stock restored successfully.";
        header("Location: recycle_bin.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Handle Delete Forever Action
if (isset($_GET['delete_forever_stock_id'])) {
    $stock_id = intval($_GET['delete_forever_stock_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM stock WHERE id = :stock_id");
        $stmt->bindParam(':stock_id', $stock_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['message'] = "Stock deleted permanently.";
        header("Location: recycle_bin.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Fetch all deleted stocks
try {
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $stmt->execute();
    $deletedStocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin</title>
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
        .card {
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
        }
        .table-container {
            overflow-x: auto;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
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
        th, td {
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
        .actions a {
            margin-right: 0.5rem;
        }
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
            table { min-width: 700px; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 1rem; }
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
        <main class="main-content">
            <div class="card p-4">
                <h4 class="mb-3"><i class="fas fa-recycle"></i> Recycle Bin</h4>
                <?php if (!empty($_SESSION['message'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
                <?php endif; ?>
                <?php if (count($deletedStocks) > 0): ?>
                    <div class="table-container">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Stock Name</th>
                                    <th>Purchase Date</th>
                                    <th>Amount Purchased</th>
                                    <th>Purchase Price (Rs.)</th>
                                    <th>Selling Price (Rs.)</th>
                                    <th>Deleted At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deletedStocks as $stock): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($stock['stock_name']) ?></td>
                                        <td><?= htmlspecialchars($stock['purchase_date']) ?></td>
                                        <td><?= htmlspecialchars($stock['amount_purchased']) ?></td>
                                        <td>Rs. <?= number_format($stock['purchase_price'], 2) ?></td>
                                        <td>Rs. <?= number_format($stock['selling_price'], 2) ?></td>
                                        <td><?= htmlspecialchars($stock['deleted_at']) ?></td>
                                        <td>
                                            <a href="recycle_bin.php?restore_stock_id=<?= $stock['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-undo"></i> Restock</a>
                                            <a href="recycle_bin.php?delete_forever_stock_id=<?= $stock['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this stock permanently?');"><i class="fas fa-trash"></i> Delete Forever</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No records found in the recycle bin.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
