<?php
session_start();

// Include database configuration
if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found. Please check the path to config.php.");
}

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../../login.php");
    exit;
}

$error = '';
$success = '';

// Fetch all ads
try {
    $stmt = $pdo->prepare("SELECT * FROM ad_tracking ORDER BY placement_date DESC");
    $stmt->execute();
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle daily orders update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_id = intval($_POST['ad_id']);
    $order_day = intval($_POST['order_day']);
    $orders = intval($_POST['orders']);

    if (empty($ad_id) || $order_day < 1 || empty($orders) || $orders < 0) {
        $error = "Invalid input for orders.";
    } else {
        try {
            // Fetch the ad to validate the day range
            $stmt = $pdo->prepare("SELECT daily_orders, duration_days FROM ad_tracking WHERE ad_id = :ad_id");
            $stmt->execute([':ad_id' => $ad_id]);
            $ad = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order_day > $ad['duration_days']) {
                $error = "Day must be between 1 and the ad's duration.";
            } else {
                $daily_orders = json_decode($ad['daily_orders'], true) ?? [];
                $daily_orders[$order_day] = $orders;

                // Update daily_orders in the database
                $stmt = $pdo->prepare("UPDATE ad_tracking SET daily_orders = :daily_orders WHERE ad_id = :ad_id");
                $stmt->execute([
                    ':daily_orders' => json_encode($daily_orders),
                    ':ad_id' => $ad_id
                ]);
                $success = "Orders updated successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ads</title>
    <link rel="stylesheet" href="../assets/css/view_ads.css">
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
                <a href="../facebook/view_ads.php" class="active"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="title">
                <h1><i class="fas fa-eye"></i>Ads</h1>
                <a href="record_ad.php"><i class="fas fa-plus-circle"></i> Record Ad</a>
            </div>
            <div class="ads-table">
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Ad Name</th>
                                <th>Cost</th>
                                <th>Placement Date</th>
                                <th>Duration</th>
                                <th>Daily Orders</th>
                                <th>Update Orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ads)): ?>
                                <?php foreach ($ads as $ad): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ad['ad_name']) ?></td>
                                        <td>$<?= number_format($ad['cost'], 2) ?></td>
                                        <td><?= htmlspecialchars($ad['placement_date']) ?></td>
                                        <td><?= htmlspecialchars($ad['duration_days']) ?> Days</td>
                                        <td>
                                            <?php
                                            $daily_orders = json_decode($ad['daily_orders'], true) ?? [];
                                            if (!empty($daily_orders)) {
                                                echo "<table class='sub-table'>";
                                                echo "<thead><tr><th style='background-color: #3498db; color: white;'>Day</th><th style='background-color: #3498db; color: white;'>Orders</th></tr></thead><tbody>";
                                                foreach ($daily_orders as $day => $count) {
                                                    echo "<tr><td>$day</td><td>$count</td></tr>";
                                                }
                                                echo "</tbody></table>";
                                            } else {
                                                echo "No orders recorded.";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="view_ads.php">
                                                <input type="hidden" name="ad_id" value="<?= $ad['ad_id'] ?>">
                                                <label for="order_day_<?= $ad['ad_id'] ?>"><i class="fas fa-calendar-day"></i> Day:</label>
                                                <input type="number" name="order_day" id="order_day_<?= $ad['ad_id'] ?>" min="1" max="<?= $ad['duration_days'] ?>" placeholder="Day" required>
                                                <label for="orders_<?= $ad['ad_id'] ?>"><i class="fas fa-box"></i> Orders:</label>
                                                <input type="number" name="orders" id="orders_<?= $ad['ad_id'] ?>" min="0" placeholder="Orders" required>
                                                <button type="submit"><i class="fas fa-save"></i> Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No ads recorded.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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