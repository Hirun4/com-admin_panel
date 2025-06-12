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
    <link rel="stylesheet" href="../assets/css/edit.css">
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
                <a href="../products/manage_products.php" ><i class="fas fa-boxes"></i> Manage Products</a>
                <a href="../orders/manage_orders.php" ><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php" class="active"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="main-content edit-content">
            <header class="title">
                <div class="title-text">
                    <a href="../expenses/manage_expenses.php"><i class="fas fa-chevron-left"></i>BACK</a>
                    <h1>
                        <i class="fas fa-edit"></i> Edit Expense
                    </h1>
                </div>
            </header>
            <div class="container">
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-group">
                        <label for="spender_name"><i class="fas fa-user"></i> Spender Name:</label>
                        <input type="text" id="spender_name" name="spender_name" value="<?= htmlspecialchars($expense['spender_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-pencil-alt"></i> Description:</label>
                        <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($expense['description']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="amount"><i class="fas fa-money-bill-wave"></i> Amount:</label>
                        <input type="number" id="amount" name="amount" step="0.01" value="<?= htmlspecialchars($expense['amount']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="expense_date"><i class="fas fa-calendar-day"></i> Expense Date:</label>
                        <input type="date" id="expense_date" name="expense_date" value="<?= htmlspecialchars($expense['expense_date']) ?>" required>
                    </div>

                    <button type="submit"><i class="fas fa-save"></i> Update Expense</button>
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
