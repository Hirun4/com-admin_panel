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
    <link rel="stylesheet" href="../assets/css/expenses.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
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
                <h1 class="top-bar"><i class="fas fa-file-invoice-dollar"></i> Expenses Table</h1>
                <a href="expenses.php"><i class="fas fa-history"></i> Previous Expenses</a>
            </div>
            <div class="content">
                <h2>Add Expense</h2>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form id="expenseForm" action="" method="POST">
                    <div class="form-group">
                        <label for="spender_name">Spender Name:</label>
                        <input type="text" id="spender_name" name="spender_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount:</label>
                        <input type="number" id="amount" name="amount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="expense_date">Date:</label>
                        <input type="date" id="expense_date" name="expense_date" required>
                    </div>
                    <button type="submit">Add Expense</button>
                </form>

                <h2>Expense Records</h2>
                <table>
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
                                <td colspan="5">No expenses recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                    <td><?= htmlspecialchars($expense['description']) ?></td>
                                    <td>Rs. <?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                    <td class="actions">
                                        <a class="edit" href="edit_expense.php?expense_id=<?= $expense['id'] ?>"><i class="fas fa-edit"></i> Edit</a> 
                                        <a class="delete" href="delete_expense.php?expense_id=<?= $expense['id'] ?>" onclick="return confirm('Are you sure you want to delete this expense?');"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

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
