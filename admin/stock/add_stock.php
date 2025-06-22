<?php
session_start();
include_once '../config/config.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$stock_id = isset($_GET['stock_id']) ? intval($_GET['stock_id']) : null;
$error = '';
$success = '';

// Fetch existing stock details if stock_id is provided
$stock = null;
$investor_details = [];
if ($stock_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM stock WHERE id = ?");
        $stmt->execute([$stock_id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            header("Location: stock_management.php");
            exit;
        }

        // Decode existing investor details
        if (!empty($stock['investor_details'])) {
            $investor_details = json_decode($stock['investor_details'], true);
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock_name = trim($_POST['stock_name'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? '';
    $amount_purchased = (int)($_POST['amount_purchased'] ?? 0);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0.0);
    $selling_price = (float)($_POST['selling_price'] ?? 0.0);

    // Collect investor details
    $investor_names = $_POST['investor_name'] ?? []; // Default to empty array
    $amounts_invested = $_POST['amount_invested'] ?? []; // Default to empty array
    $investor_details = [];

    foreach ($investor_names as $index => $investor_name) {
        // Ensure both investor name and amount are valid
        if (!empty($investor_name) && isset($amounts_invested[$index]) && !empty($amounts_invested[$index])) {
            $investor_details[] = [
                'investor_name' => htmlspecialchars(trim($investor_name)), // Sanitize input
                'amount_invested' => (float)$amounts_invested[$index], // Convert to float
            ];
        }
    }

    // Validate required fields
    if (empty($stock_name) || empty($purchase_date) || $amount_purchased <= 0 || $purchase_price <= 0 || $selling_price <= 0) {
        $error = "Please fill out all required fields correctly.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($stock_id) {
                // Update stock details
                $stmt = $pdo->prepare("
                    UPDATE stock 
                    SET stock_name = ?, purchase_date = ?, amount_purchased = ?, purchase_price = ?, selling_price = ?, investor_details = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $stock_name,
                    $purchase_date,
                    $amount_purchased,
                    $purchase_price,
                    $selling_price,
                    json_encode($investor_details),
                    $stock_id
                ]);
            } else {
                // Insert new stock entry
                $stmt = $pdo->prepare("
                    INSERT INTO stock (stock_name, purchase_date, amount_purchased, purchase_price, selling_price, investor_details) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $stock_name,
                    $purchase_date,
                    $amount_purchased,
                    $purchase_price,
                    $selling_price,
                    json_encode($investor_details)
                ]);
            }

            $pdo->commit();
            $success = $stock_id ? 'Stock updated successfully!' : 'Stock and investors added successfully!';
            header("Location: stock_management.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
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
    <title><?= $stock_id ? 'Edit Stock' : 'Add Stock' ?></title>
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
        .investor-entry {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .investor-entry label {
            min-width: 120px;
            margin-bottom: 0;
        }
        @media (max-width: 991px) {
            .main-content { padding: 1rem; }
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
            <div class="card p-4 mb-4">
                <h4 class="mb-3"><?= $stock_id ? 'Edit Stock' : 'Add Stock' ?></h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="stock_name" class="form-label"><i class="fas fa-box"></i> Stock Name:</label>
                        <input type="text" id="stock_name" name="stock_name" class="form-control" value="<?= htmlspecialchars($stock['stock_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="purchase_date" class="form-label"><i class="fas fa-calendar-alt"></i> Purchase Date:</label>
                        <input type="date" id="purchase_date" name="purchase_date" class="form-control" value="<?= htmlspecialchars($stock['purchase_date'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="amount_purchased" class="form-label"><i class="fas fa-boxes"></i> Amount Purchased:</label>
                        <input type="number" id="amount_purchased" name="amount_purchased" class="form-control" value="<?= htmlspecialchars($stock['amount_purchased'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="purchase_price" class="form-label"><i class="fas fa-dollar-sign"></i> Purchase Price (per unit) (Rs.):</label>
                        <input type="number" step="0.01" id="purchase_price" name="purchase_price" class="form-control" value="<?= htmlspecialchars($stock['purchase_price'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="selling_price" class="form-label"><i class="fas fa-tag"></i> Selling Price (per unit) (Rs.):</label>
                        <input type="number" step="0.01" id="selling_price" name="selling_price" class="form-control" value="<?= htmlspecialchars($stock['selling_price'] ?? '') ?>" required>
                    </div>

                    <h3 class="mt-4"><i class="fas fa-users"></i> Investor Details</h3>
                    <div id="investors-container">
                        <?php if ($investor_details): ?>
                            <?php foreach ($investor_details as $investor): ?>
                                <div class="investor-entry">
                                    <div class="form-group flex-fill">
                                        <label class="form-label">Investor Name:</label>
                                        <input type="text" name="investor_name[]" class="form-control" value="<?= htmlspecialchars($investor['investor_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group flex-fill">
                                        <label class="form-label">Amount Invested (Rs.):</label>
                                        <input type="number" step="0.01" name="amount_invested[]" class="form-control" value="<?= htmlspecialchars($investor['amount_invested'] ?? '') ?>" required>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="investor-entry">
                                <div class="form-group flex-fill">
                                    <label class="form-label">Investor Name:</label>
                                    <input type="text" name="investor_name[]" class="form-control" required>
                                </div>
                                <div class="form-group flex-fill">
                                    <label class="form-label">Amount Invested (Rs.):</label>
                                    <input type="number" step="0.01" name="amount_invested[]" class="form-control" required>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-investor-button" class="btn btn-outline-primary mt-3"><i class="fas fa-plus-circle"></i> Add Another Investor</button>
                    <button type="button" id="undo-investor-button" class="btn btn-outline-danger mt-3"><i class="fas fa-minus-circle"></i> Undo Last Investor</button>

                    <button type="submit" class="btn btn-primary mt-4"><i class="fas fa-save"></i> <?= $stock_id ? 'Update Stock' : 'Add Stock' ?></button>
                </form>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            // Add new investor
            $('#add-investor-button').on('click', function () {
                const newInvestor = `
                    <div class="investor-entry">
                        <div class="form-group flex-fill">
                            <label class="form-label">Investor Name:</label>
                            <input type="text" name="investor_name[]" class="form-control" placeholder="Enter investor's name" required>
                        </div>
                        <div class="form-group flex-fill">
                            <label class="form-label">Amount Invested (Rs.):</label>
                            <input type="number" step="0.01" name="amount_invested[]" class="form-control" placeholder="Enter amount invested" required>
                        </div>
                    </div>`;
                $('#investors-container').append(newInvestor);
            });

            // Undo last investor
            $('#undo-investor-button').on('click', function () {
                const investorEntries = $('#investors-container .investor-entry');
                if (investorEntries.length > 1) { // Ensure at least one entry remains
                    investorEntries.last().remove();
                } else {
                    alert('Cannot remove the last investor entry!');
                }
            });
        });
    </script>
</body>
</html>
