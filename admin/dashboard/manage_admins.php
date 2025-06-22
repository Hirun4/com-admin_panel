<?php
include_once '../config/config.php';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = intval($_POST['update_id']);

    // For each tab, set 'yes' if checked, 'no' if not
    $dashboard = isset($_POST['dashboard']) ? 'yes' : 'no';
    $Manage_products = isset($_POST['Manage_products']) ? 'yes' : 'no';
    $Manage_orders = isset($_POST['Manage_orders']) ? 'yes' : 'no';
    $Stock_Management = isset($_POST['Stock_Management']) ? 'yes' : 'no';
    $Manage_expence = isset($_POST['Manage_expence']) ? 'yes' : 'no';
    $Facebook_ads = isset($_POST['Facebook_ads']) ? 'yes' : 'no';
    $Monthly_reports = isset($_POST['Monthly_reports']) ? 'yes' : 'no';
    $Resellers = isset($_POST['Resellers']) ? 'yes' : 'no';

    $stmt = $pdo->prepare("UPDATE new_admins SET 
        dashboard = :dashboard,
        Manage_products = :Manage_products,
        Manage_orders = :Manage_orders,
        Stock_Management = :Stock_Management,
        Manage_expence = :Manage_expence,
        Facebook_ads = :Facebook_ads,
        Monthly_reports = :Monthly_reports,
        Resellers = :Resellers
        WHERE id = :id");
    $stmt->execute([
        ':dashboard' => $dashboard,
        ':Manage_products' => $Manage_products,
        ':Manage_orders' => $Manage_orders,
        ':Stock_Management' => $Stock_Management,
        ':Manage_expence' => $Manage_expence,
        ':Facebook_ads' => $Facebook_ads,
        ':Monthly_reports' => $Monthly_reports,
        ':Resellers' => $Resellers,
        ':id' => $id
    ]);
}

// Fetch admins
$stmt = $pdo->query("SELECT * FROM new_admins ORDER BY id DESC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .sidebar { min-width: 220px; background: #212529; color: #fff; min-height: 100vh; }
        .sidebar h2 { padding: 1.5rem 1rem 1rem 1rem; font-size: 1.5rem; border-bottom: 1px solid #343a40; }
        .sidebar nav a { display: block; color: #adb5bd; padding: 0.75rem 1rem; text-decoration: none; transition: background 0.2s, color 0.2s; }
        .sidebar nav a.active, .sidebar nav a:hover { background: #343a40; color: #fff; }
        .main-content { padding: 2.5rem 2rem; flex: 1; }
        .card { border-radius: 1.25rem; box-shadow: 0 4px 24px rgba(0,0,0,0.09); margin-bottom: 2rem; background: #fff; border: none; }
        .table thead { background: #212529; color: #fff; }
        .table tbody tr:hover { background: #f1f3f5; }
        .form-check-input { margin-top: 0.3rem; }
        .btn-primary { border-radius: 0.5rem; }
        .btn-primary:hover { background: #0056b3; }
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
                <a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <?php
                // Main admin: show all tabs
                if (isset($_SESSION['admin_logged_in']) && !isset($_SESSION['is_other_admin'])) {
                ?>
                    <a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>
                    <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                    <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                    <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                    <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                    <a href="monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                    <a href="resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                    <a href="add_admin.php"><i class="fas fa-user"></i> Add Admin</a>
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
                            echo '<a href="monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>';
                        }
                        if ($admin['Resellers'] === 'yes') {
                            echo '<a href="resellers.php"><i class="fas fa-user"></i> Re Sellers</a>';
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
            <div class="card p-4">
                <h2 class="mb-4"><i class="fas fa-users-cog"></i> Manage Admins</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Phone Number</th>
                                <th>Dashboard</th>
                                <th>Manage Products</th>
                                <th>Manage Orders</th>
                                <th>Stock Management</th>
                                <th>Manage Expence</th>
                                <th>Facebook Ads</th>
                                <th>Monthly Reports</th>
                                <th>Resellers</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <form method="post">
                                    <td><?= htmlspecialchars($admin['id']); ?></td>
                                    <td><?= htmlspecialchars($admin['name']); ?></td>
                                    <td><?= htmlspecialchars($admin['address']); ?></td>
                                    <td><?= htmlspecialchars($admin['phone_number']); ?></td>
                                    <td><input type="checkbox" name="dashboard" class="form-check-input" <?= $admin['dashboard'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Manage_products" class="form-check-input" <?= $admin['Manage_products'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Manage_orders" class="form-check-input" <?= $admin['Manage_orders'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Stock_Management" class="form-check-input" <?= $admin['Stock_Management'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Manage_expence" class="form-check-input" <?= $admin['Manage_expence'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Facebook_ads" class="form-check-input" <?= $admin['Facebook_ads'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Monthly_reports" class="form-check-input" <?= $admin['Monthly_reports'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td><input type="checkbox" name="Resellers" class="form-check-input" <?= $admin['Resellers'] === 'yes' ? 'checked' : '' ?>></td>
                                    <td>
                                        <input type="hidden" name="update_id" value="<?= $admin['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-center">
                    <a href="add_admin.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user-plus"></i> Add Admin</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>