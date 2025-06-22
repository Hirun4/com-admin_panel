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

// Initialize variables
$error = '';
$success = '';

// Handle form submission for adding an expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spender_name = $_POST['spender_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $expense_date = $_POST['expense_date'] ?? '';

    if (empty($spender_name) || empty($description) || empty($amount) || empty($expense_date)) {
        $error = 'All fields are required.';
    } elseif (!is_numeric($amount)) {
        $error = 'Amount must be a numeric value.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (spender_name, description, amount, expense_date) VALUES (:spender_name, :description, :amount, :expense_date)");
            $stmt->execute([
                ':spender_name' => $spender_name,
                ':description' => $description,
                ':amount' => $amount,
                ':expense_date' => $expense_date,
            ]);
            $success = 'Expense added successfully!';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch all expenses
try {
    $stmt = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching expenses: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Table</title>
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
            font-size: 2rem;
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
        .alert {
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
        }
        .actions a {
            margin-right: 0.5rem;
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
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Expenses Table</span>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fas fa-history"></i> Previous Expenses</a>
            </div>
            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card p-4">
                        <h4 class="mb-3">Add Expense</h4>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <form id="expenseForm" action="" method="POST">
                            <div class="mb-3">
                                <label for="spender_name" class="form-label">Spender Name</label>
                                <input type="text" id="spender_name" name="spender_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" rows="3" class="form-control" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" id="amount" name="amount" step="0.01" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="expense_date" class="form-label">Date</label>
                                <input type="date" id="expense_date" name="expense_date" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add Expense</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card p-4">
                        <h4 class="mb-3">Expense Records</h4>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Spender Name</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No expenses recorded yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                                <td><?= htmlspecialchars($expense['description']) ?></td>
                                                <td><span class="badge bg-success">Rs. <?= number_format($expense['amount'], 2) ?></span></td>
                                                <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                                <td class="actions">
                                                    <a class="btn btn-sm btn-outline-primary" href="edit_expense.php?expense_id=<?= $expense['id'] ?>"><i class="fas fa-edit"></i></a>
                                                    <a class="btn btn-sm btn-outline-danger" href="delete_expense.php?expense_id=<?= $expense['id'] ?>" onclick="return confirm('Are you sure you want to delete this expense?');"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('expenseForm').addEventListener('submit', function(event) {
            let spender_name = document.getElementById('spender_name').value.trim();
            let description = document.getElementById('description').value.trim();
            let amount = document.getElementById('amount').value.trim();
            let expense_date = document.getElementById('expense_date').value.trim();

            if (!spender_name || !description || !amount || !expense_date) {
                alert('All fields are required.');
                event.preventDefault();
            } else if (isNaN(amount) || parseFloat(amount) <= 0) {
                alert('Amount must be a valid number greater than zero.');
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
