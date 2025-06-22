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

// Check if expense_id is provided
if (!isset($_GET['expense_id'])) {
    header("Location: manage_expenses.php");
    exit;
}



$expense_id = intval($_GET['expense_id']);
$error = '';
$success = '';

// Fetch expense details
try {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = :expense_id");
    $stmt->execute([':expense_id' => $expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        header("Location: manage_expenses.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spender_name = $_POST['spender_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $expense_date = $_POST['expense_date'] ?? '';

    // Validate input
    if (empty($spender_name) || empty($description) || empty($amount) || empty($expense_date)) {
        $error = 'All fields are required.';
    } elseif (!is_numeric($amount)) {
        $error = 'Amount must be a numeric value.';
    }

    if (empty($error)) {
        try {
            // Update expense details
            $stmt = $pdo->prepare(
                "UPDATE expenses SET spender_name = :spender_name, description = :description, amount = :amount, expense_date = :expense_date WHERE id = :expense_id"
            );
            $stmt->execute([
                ':spender_name' => $spender_name,
                ':description' => $description,
                ':amount' => $amount,
                ':expense_date' => $expense_date,
                ':expense_id' => $expense_id,
            ]);

            $success = 'Expense updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense</title>
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
        .edit-header {
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
        .alert {
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
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
            <div class="edit-header mb-4">
                <a href="../expenses/manage_expenses.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-left"></i> BACK</a>
                <span><i class="fas fa-edit"></i> Edit Expense</span>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label for="spender_name" class="form-label"><i class="fas fa-user"></i> Spender Name</label>
                                <input type="text" id="spender_name" name="spender_name" class="form-control" value="<?= htmlspecialchars($expense['spender_name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label"><i class="fas fa-pencil-alt"></i> Description</label>
                                <textarea id="description" name="description" rows="3" class="form-control" required><?= htmlspecialchars($expense['description']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label"><i class="fas fa-money-bill-wave"></i> Amount</label>
                                <input type="number" id="amount" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($expense['amount']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="expense_date" class="form-label"><i class="fas fa-calendar-day"></i> Expense Date</label>
                                <input type="date" id="expense_date" name="expense_date" class="form-control" value="<?= htmlspecialchars($expense['expense_date']) ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Update Expense</button>
                        </form>
                    </div>
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
