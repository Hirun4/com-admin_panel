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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_name = $_POST['ad_name'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $placement_date = $_POST['placement_date'] ?? '';
    $duration_days = $_POST['duration_days'] ?? '';

    if (empty($ad_name) || empty($cost) || empty($placement_date) || empty($duration_days)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($cost) || $cost <= 0 || !is_numeric($duration_days) || $duration_days <= 0) {
        $error = "Cost and duration must be positive numbers.";
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ad_tracking (ad_name, cost, placement_date, duration_days)
                VALUES (:ad_name, :cost, :placement_date, :duration_days)
            ");
            $stmt->execute([
                ':ad_name' => $ad_name,
                ':cost' => $cost,
                ':placement_date' => $placement_date,
                ':duration_days' => $duration_days
            ]);
            $success = "Ad recorded successfully!";
            header("Location: view_ads.php");
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
    <title>Record Ad</title>
    <link rel="stylesheet" href="../assets/css/record_ad.css">
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
                <h1><i class="fas fa-pen"></i> Record New Ad</h1>
                <a href="../facebook/view_ads.php" ></i> View All Ad</a>
            </div>
            <div class="form-container">
                <h2 style="text-align: center; font-size: 1.8rem; color: #333;"><i class="fas fa-plus-circle"></i> Record a New Ad</h2>
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form action="record_ad.php" method="POST">
                    <div class="form-group">
                        <label for="ad_name"><i class="fas fa-font"></i> Ad Name:</label>
                        <input type="text" id="ad_name" name="ad_name" required>
                    </div>
                    <div class="form-group">
                        <label for="cost"><i class="fas fa-dollar-sign"></i> Cost:</label>
                        <input type="number" id="cost" name="cost" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="placement_date"><i class="fas fa-calendar-day"></i> Placement Date:</label>
                        <input type="date" id="placement_date" name="placement_date" required>
                    </div>
                    <div class="form-group">
                        <label for="duration_days"><i class="fas fa-calendar-week"></i> Duration (Days):</label>
                        <input type="number" id="duration_days" name="duration_days" required>
                    </div>
                    <button type="submit"><i class="fas fa-save"></i> Record Ad</button>
                </form>
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
