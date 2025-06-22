<?php
session_start();

// Include the database configuration
if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found. Please check the path to config.php.");
}

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Fetch all current month's expenses grouped by 1st-15th and 16th-end of the month
$currentMonth = date('Y-m');
try {
    $stmt = $pdo->prepare("SELECT 
            spender_name, 
            description, 
            amount, 
            expense_date,
            CASE 
                WHEN DAY(expense_date) <= 15 THEN '1-15'
                ELSE '16-31'
            END AS period 
        FROM expenses 
        WHERE DATE_FORMAT(expense_date, '%Y-%m') = :current_month
        ORDER BY expense_date ASC");
    $stmt->execute([':current_month' => $currentMonth]);
    $currentExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching current month's expenses: " . $e->getMessage());
}

// Fetch all previous months' expenses grouped by month and spender
try {
    $stmt = $pdo->prepare("SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') AS month, 
            spender_name, 
            SUM(amount) AS total_amount 
        FROM expenses 
        WHERE expense_date < :current_date 
        GROUP BY DATE_FORMAT(expense_date, '%Y-%m'), spender_name
        ORDER BY DATE_FORMAT(expense_date, '%Y-%m') DESC");
    $stmt->execute([':current_date' => "$currentMonth-01"]);
    $allPreviousExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching previous months' expenses: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
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
            padding: 2rem;
            flex: 1;
        }
        .top-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
        }
        .table thead {
            background: #212529;
            color: #fff;
        }
        .table tbody tr:hover {
            background: #f1f3f5;
        }
        .section-title {
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: #212529;
        }
        .period-title {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            color: #495057;
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
            <div class="top-bar">
                <a href="../expenses/manage_expenses.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-left"></i> BACK</a>
                <span><i class="fas fa-file-invoice-dollar"></i> Expenses</span>
            </div>
            <div>
                <div class="card p-4 mb-4">
                    <h4 class="section-title">Current Month's Expenses (<?= date('F Y') ?>)</h4>
                    <div class="period-title">1st-15th</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-4">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Spender Name</th>
                                    <th><i class="fas fa-pencil-alt"></i> Description</th>
                                    <th><i class="fas fa-money-bill-wave"></i> Amount</th>
                                    <th><i class="fas fa-calendar-day"></i> Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $found = false;
                                foreach ($currentExpenses as $expense) {
                                    if ($expense['period'] === '1-15') {
                                        $found = true;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                            <td><?= htmlspecialchars($expense['description']) ?></td>
                                            <td><span class="badge bg-success">Rs. <?= number_format($expense['amount'], 2) ?></span></td>
                                            <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                        </tr>
                                        <?php
                                    }
                                }
                                if (!$found) {
                                    echo '<tr><td colspan="4" class="text-center text-muted">No expenses found for this period.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="period-title">16th-31st</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-4">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Spender Name</th>
                                    <th><i class="fas fa-pencil-alt"></i> Description</th>
                                    <th><i class="fas fa-money-bill-wave"></i> Amount</th>
                                    <th><i class="fas fa-calendar-day"></i> Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $found = false;
                                foreach ($currentExpenses as $expense) {
                                    if ($expense['period'] === '16-31') {
                                        $found = true;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                            <td><?= htmlspecialchars($expense['description']) ?></td>
                                            <td><span class="badge bg-success">Rs. <?= number_format($expense['amount'], 2) ?></span></td>
                                            <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                        </tr>
                                        <?php
                                    }
                                }
                                if (!$found) {
                                    echo '<tr><td colspan="4" class="text-center text-muted">No expenses found for this period.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card p-4">
                    <h4 class="section-title">Previous Months' Expenses</h4>
                    <?php
                    $groupedExpenses = [];
                    foreach ($allPreviousExpenses as $expense) {
                        $groupedExpenses[$expense['month']][] = $expense;
                    }
                    ?>
                    <?php if (empty($groupedExpenses)): ?>
                        <p class="text-muted">No previous months' expenses found.</p>
                    <?php else: ?>
                        <?php foreach ($groupedExpenses as $month => $expenses): ?>
                            <div class="period-title"><?= date('F Y', strtotime($month . '-01')) ?></div>
                            <div class="table-responsive mb-4">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-user"></i> Spender Name</th>
                                            <th><i class="fas fa-money-bill-wave"></i> Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                                <td><span class="badge bg-primary">Rs. <?= number_format($expense['total_amount'], 2) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for active sidebar link
        document.querySelectorAll('.sidebar a').forEach(function(link) {
            if (window.location.pathname.includes(link.getAttribute('href'))) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>
