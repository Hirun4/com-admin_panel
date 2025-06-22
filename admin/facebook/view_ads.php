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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
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

        .alert {
            margin-bottom: 1rem;
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

        .sub-table {
            width: 100%;
            margin-top: 0.5rem;
            border-radius: 0.5rem;
            background: #f8fafc;
        }

        .sub-table th,
        .sub-table td {
            padding: 0.3rem 0.5rem;
            font-size: 0.93em;
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

        .alert-error {
            background: #ffeaea;
            color: #b71c1c;
            border: 1px solid #f5c6cb;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #c3e6cb;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
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
                <a href="/project/admin/dashboard/index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
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
                <i class="fas fa-eye"></i> Ads
                <a href="record_ad.php" class="btn btn-success btn-sm"><i class="fas fa-plus-circle"></i> Record Ad</a>
            </div>
            <div class="card p-4">
                <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
                <?php endif; ?>
                <div class="table-container">
                    <table class="table align-middle">
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
                                        <input type="number" name="order_day" id="order_day_<?= $ad['ad_id'] ?>" min="1"
                                            max="<?= $ad['duration_days'] ?>" placeholder="Day" required>
                                        <label for="orders_<?= $ad['ad_id'] ?>"><i class="fas fa-box"></i> Orders:</label>
                                        <input type="number" name="orders" id="orders_<?= $ad['ad_id'] ?>" min="0"
                                            placeholder="Orders" required>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.sidebar a').forEach(function(link) {
            if (window.location.pathname.includes(link.getAttribute('href'))) {
                link.classList.add('active');
            }
        });
    </script>
</body>

</html>