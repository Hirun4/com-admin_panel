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
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
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
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.15rem rgba(0,123,255,.15);
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .btn-primary, .btn-outline-primary {
            border-radius: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #0056b3 0%, #007bff 100%);
        }
        .btn-outline-primary {
            border: 1.5px solid #007bff;
            color: #007bff;
        }
        .btn-outline-primary:hover {
            background: #007bff;
            color: #fff;
        }
        @media (max-width: 991px) {
            .main-content { padding: 1rem; }
            .title { font-size: 1.2rem; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 0.5rem; }
            .card { padding: 1rem; }
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
            <div class="title">
                <i class="fas fa-pen"></i> Record New Ad
                <a href="view_ads.php" class="btn btn-outline-primary btn-sm ms-auto"><i class="fas fa-eye"></i> View All Ads</a>
            </div>
            <div class="card p-4" style="max-width: 600px; margin: 0 auto;">
                <h2 class="mb-4" style="text-align: center; font-size: 1.5rem; color: #333;"><i class="fas fa-plus-circle"></i> Record a New Ad</h2>
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form action="record_ad.php" method="POST">
                    <div class="form-group">
                        <label for="ad_name" class="form-label"><i class="fas fa-font"></i> Ad Name:</label>
                        <input type="text" id="ad_name" name="ad_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="cost" class="form-label"><i class="fas fa-dollar-sign"></i> Cost:</label>
                        <input type="number" id="cost" name="cost" step="0.01" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="placement_date" class="form-label"><i class="fas fa-calendar-day"></i> Placement Date:</label>
                        <input type="date" id="placement_date" name="placement_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="duration_days" class="form-label"><i class="fas fa-calendar-week"></i> Duration (Days):</label>
                        <input type="number" id="duration_days" name="duration_days" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Record Ad</button>
                </form>
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
