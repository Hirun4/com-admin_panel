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

        $stmt = $pdo->prepare("
        SELECT 
            SUM(oi.final_price) AS total_income,
            SUM(oi.final_price - (SELECT pc.buying_price FROM product_codes pc WHERE pc.code = oi.buying_price_code)*oi.quantity) AS profit
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.delivery_method = 'Home' AND DATE(o.created_at) = :dateFilter
    ");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $homeStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT 
            SUM(oi.final_price) AS total_income,
            SUM(oi.final_price - (SELECT pc.buying_price FROM product_codes pc WHERE pc.code = oi.buying_price_code)*oi.quantity) AS profit
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.delivery_method = 'Courier' AND DATE(o.created_at) = :dateFilter
    ");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $courierStats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Total Revenue for the month
$stmt = $pdo->prepare("
    SELECT SUM(oi.final_price) AS total_revenue 
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.created_at BETWEEN :startDate AND :endDate
");
$stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
$totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

// Total Profit for the month
$stmt = $pdo->prepare("
    SELECT 
        SUM(oi.final_price - (SELECT pc.buying_price FROM product_codes pc WHERE pc.code = oi.buying_price_code)*oi.quantity) AS total_profit
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.created_at BETWEEN :startDate AND :endDate
");
$stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
$totalProfit = $stmt->fetch(PDO::FETCH_ASSOC)['total_profit'] ?? 0;

// Fetch total refund amount and total buying price for approved refunds in the selected month
$stmt = $pdo->prepare("
    SELECT 
        SUM(rr.refund_amount) AS total_refund,
        SUM(
            rr.refund_amount - IFNULL((
                SELECT SUM(oi.quantity * IFNULL(pc.buying_price, 0))
                FROM order_items oi
                LEFT JOIN product_codes pc ON oi.buying_price_code = pc.code
                WHERE oi.order_id = rr.order_id
            ), 0)
        ) AS total_refund_profit
    FROM refund_requests rr
    WHERE rr.status = 'APPROVED'
      AND EXISTS (
          SELECT 1 FROM orders o WHERE o.order_id = rr.order_id
            AND o.created_at BETWEEN :startDate AND :endDate
      )
");
$stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
$refundStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalRefund = $refundStats['total_refund'] ?? 0;
$totalRefundProfit = $refundStats['total_refund_profit'] ?? 0;

// Adjust revenue and profit
$totalRevenue = ($totalRevenue ?? 0) - $totalRefund;
$totalProfit = ($totalProfit ?? 0) - $totalRefundProfit;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report</title>
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
        .title { display: flex; align-items: center; gap: 1rem; font-size: 2rem; margin-bottom: 2rem; font-weight: 700; color: #212529; }
        .title a { margin-left: auto; font-size: 1rem; }
        .table thead { background: #212529; color: #fff; }
        .table tbody tr:hover { background: #f1f3f5; }
        .section-title { margin-top: 2rem; margin-bottom: 1rem; font-weight: 600; color: #212529; }
        .alert { margin-bottom: 1rem; }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .filter-form label {
            font-weight: 600;
            color: #495057;
            margin-right: 0.5rem;
        }
        .filter-form select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #fff;
            padding: 0.4rem 1rem;
            font-size: 1rem;
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
            .main-content { padding: 1rem; }
            .title { font-size: 1.2rem; }
            .filter-form { flex-direction: column; align-items: flex-start; gap: 0.7rem; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 1rem; }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <div class="title">
                <i class="fas fa-chart-line"></i> Monthly Report
            </div>
            <div class="card p-4">
                <form method="GET" action="" class="filter-form mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <label for="year"><i class="fas fa-calendar"></i> Year</label>
                        <select name="year" id="year">
                            <?php
                            for ($i = date('Y'); $i >= 2022; $i--) {
                                $selected = ($i == $filterYear) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="month"><i class="fas fa-calendar-alt"></i> Month</label>
                        <select name="month" id="month">
                            <?php
                            for ($i = 1; $i <= 12; $i++) {
                                $selected = ($i == $filterMonth) ? 'selected' : '';
                                echo "<option value='" . str_pad($i, 2, '0', STR_PAD_LEFT) . "' $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                </form>

                <!-- Monthly Report Table -->
                <!-- <section class="monthly-report">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
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
                    </div>
                </section> -->
                

                <!-- Monthly Totals -->
                <div class="monthly-totals-section" style="margin-top:2.5rem;">
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-6 col-lg-5">
                            <div class="monthly-total-card" style="
                                background: linear-gradient(90deg, #2980b9 0%, #6dd5fa 100%);
                                border-radius: 1.5rem;
                                box-shadow: 0 6px 32px rgba(41,128,185,0.13);
                                padding: 2.2rem 2rem 1.5rem 2rem;
                                color: #fff;
                                display: flex;
                                align-items: center;
                                gap: 1.5rem;
                                min-height: 120px;
                            ">
                                <div style="font-size:2.7rem; background:rgba(255,255,255,0.13); border-radius:1rem; padding:0.7rem 1.2rem;">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div>
                                    <div style="font-size:1.1rem; font-weight:500; letter-spacing:0.5px; opacity:0.93;">
                                        Total Revenue
                                    </div>
                                    <div style="font-size:1.7rem; font-weight:700; letter-spacing:1px;">
                                        Rs. <?= number_format($totalRevenue, 2) ?>
                                    </div>
                                    <div style="font-size:1rem; opacity:0.8;">
                                        for <?= date('F Y', strtotime($startDate)) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-5">
                            <div class="monthly-total-card" style="
                                background: linear-gradient(90deg, #27ae60 0%, #6dd5fa 100%);
                                border-radius: 1.5rem;
                                box-shadow: 0 6px 32px rgba(39,174,96,0.13);
                                padding: 2.2rem 2rem 1.5rem 2rem;
                                color: #fff;
                                display: flex;
                                align-items: center;
                                gap: 1.5rem;
                                min-height: 120px;
                            ">
                                <div style="font-size:2.7rem; background:rgba(255,255,255,0.13); border-radius:1rem; padding:0.7rem 1.2rem;">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div>
                                    <div style="font-size:1.1rem; font-weight:500; letter-spacing:0.5px; opacity:0.93;">
                                        Total Profit
                                    </div>
                                    <div style="font-size:1.7rem; font-weight:700; letter-spacing:1px;">
                                        Rs. <?= number_format($totalProfit, 2) ?>
                                    </div>
                                    <div style="font-size:1rem; opacity:0.8;">
                                        for <?= date('F Y', strtotime($startDate)) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Monthly Totals -->
            </div>
        </div>
    </div>
</body>

</html>