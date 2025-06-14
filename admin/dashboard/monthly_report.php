<?php
session_start();
include_once '../config/config.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Set default filter to current year and month
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('m');

// Format the start and end date based on selected month and year
$startDate = "$filterYear-$filterMonth-01";
$endDate = date("Y-m-t", strtotime($startDate)); // Last day of the selected month

// Fetch revenue and profit for Home and Courier delivery for the selected month
try {
    $deliveryMethods = ['Home', 'Courier'];
    $stats = [];

    foreach ($deliveryMethods as $method) {
        $stmt = $pdo->prepare("SELECT 
                SUM(oi.selling_price - oi.discount) AS total_income,
                SUM(oi.selling_price - (SELECT pc.buying_price FROM product_codes pc WHERE pc.code = oi.buying_price_code) - oi.discount) AS profit
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.delivery_method = :deliveryMethod AND o.created_at BETWEEN :startDate AND :endDate");
        $stmt->bindParam(':deliveryMethod', $method);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $stats[$method] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report</title>
    <link rel="stylesheet" href="../assets/css/monthly_report.css">
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
                <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php" class="active"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div>
                <h1>Monthly Report for <?= date('F Y', strtotime($startDate)) ?></h1>
            </div>

            <!-- Filter Form -->
            <section class="filter-form">
                <form method="GET" action="">
                    <div>
                        <label for="year">Year:</label>
                        <select name="year" id="year">
                            <?php
                            // Generate a list of years from 2022 to the current year
                            for ($i = date('Y'); $i >= 2022; $i--) {
                                $selected = ($i == $filterYear) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="month">Month:</label>
                        <select name="month" id="month">
                            <?php
                            // Generate months dropdown
                            for ($i = 1; $i <= 12; $i++) {
                                $selected = ($i == $filterMonth) ? 'selected' : '';
                                echo "<option value='" . str_pad($i, 2, '0', STR_PAD_LEFT) . "' $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                </form>
            </section>

            <!-- Monthly Report Table -->
            <section class="monthly-report">
                <table>
                    <thead>
                        <tr>
                            <th>Delivery Method</th>
                            <th>Total Revenue</th>
                            <th>Total Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $method => $data): ?>
                            <tr>
                                <td><i class="fas <?= $method === 'Home' ? 'fa-home' : 'fa-truck' ?>"></i> <?= $method ?> Delivery</td>
                                <td>Rs. <?= number_format($data['total_income'] ?? 0, 2) ?></td>
                                <td>Rs. <?= number_format($data['profit'] ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>

</html>