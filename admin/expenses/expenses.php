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
    <link rel="stylesheet" href="../assets/css/expenses.css">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="../dashboard/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>
                <a href="../orders/manage_orders.php" ><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php" class="active"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <a href="../expenses/manage_expenses.php"><i class="fas fa-chevron-left"></i>BACK</a>
                <h1 ><i class="fas fa-file-invoice-dollar"></i> Expenses</h1>
            </div>
            <div class="content1">
                <!-- Current Month's Expenses -->
                <h2>Current Month's Expenses (<?= date('F Y') ?>)</h2>
                <h3>1st-15th</h3>
                <table>
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
                                    <td>$<?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                </tr>
                                <?php
                            }
                        }
                        if (!$found) {
                            echo '<tr><td colspan="4">No expenses found for this period.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
                
                <h3>16th-31st</h3>
                <table>
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
                                    <td>$<?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                </tr>
                                <?php
                            }
                        }
                        if (!$found) {
                            echo '<tr><td colspan="4">No expenses found for this period.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Previous Months' Expenses -->
                <h2>Previous Months' Expenses</h2>
                <?php
                $groupedExpenses = [];
                foreach ($allPreviousExpenses as $expense) {
                    $groupedExpenses[$expense['month']][] = $expense;
                }
                ?>
                <?php if (empty($groupedExpenses)): ?>
                    <p>No previous months' expenses found.</p>
                <?php else: ?>
                    <?php foreach ($groupedExpenses as $month => $expenses): ?>
                        <h3><?= date('F Y', strtotime($month . '-01')) ?></h3>
                        <table>
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
                                        <td>$<?= number_format($expense['total_amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

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
