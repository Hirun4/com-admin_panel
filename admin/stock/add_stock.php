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
    <link rel="stylesheet" href="../assets/css/stock.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome for Icons -->
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
            <header>
                <h1><?= $stock_id ? 'Edit Stock' : 'Add Stock' ?></h1>
            </header>

            <section class="add-stock">
                <?php if ($error): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <?php if ($success): ?>
                    <p class="success"><?= htmlspecialchars($success) ?></p>
                <?php endif; ?>

                <form method="POST" action="">
                    <label for="stock_name"><i class="fas fa-box"></i> Stock Name:</label>
                    <input type="text" id="stock_name" name="stock_name" value="<?= htmlspecialchars($stock['stock_name'] ?? '') ?>" required>

                    <label for="purchase_date"><i class="fas fa-calendar-alt"></i> Purchase Date:</label>
                    <input type="date" id="purchase_date" name="purchase_date" value="<?= htmlspecialchars($stock['purchase_date'] ?? '') ?>" required>

                    <label for="amount_purchased"><i class="fas fa-boxes"></i> Amount Purchased:</label>
                    <input type="number" id="amount_purchased" name="amount_purchased" value="<?= htmlspecialchars($stock['amount_purchased'] ?? '') ?>" required>

                    <label for="purchase_price"><i class="fas fa-dollar-sign"></i> Purchase Price (per unit) (Rs.):</label>
                    <input type="number" step="0.01" id="purchase_price" name="purchase_price" value="<?= htmlspecialchars($stock['purchase_price'] ?? '') ?>" required>

                    <label for="selling_price"><i class="fas fa-tag"></i> Selling Price (per unit) (Rs.):</label>
                    <input type="number" step="0.01" id="selling_price" name="selling_price" value="<?= htmlspecialchars($stock['selling_price'] ?? '') ?>" required>

                    <h3><i class="fas fa-users"></i> Investor Details</h3>
                    <div id="investors-container">
                        <?php if ($investor_details): ?>
                            <?php foreach ($investor_details as $investor): ?>
                                <div class="investor-entry">
                                    <label>Investor Name:</label>
                                    <input type="text" name="investor_name[]" value="<?= htmlspecialchars($investor['investor_name'] ?? '') ?>" required>
                                    <label>Amount Invested (Rs.):</label>
                                    <input type="number" step="0.01" name="amount_invested[]" value="<?= htmlspecialchars($investor['amount_invested'] ?? '') ?>" required>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="investor-entry">
                                <label>Investor Name:</label>
                                <input type="text" name="investor_name[]" required>
                                <label>Amount Invested (Rs.):</label>
                                <input type="number" step="0.01" name="amount_invested[]" required>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-investor-button"><i class="fas fa-plus-circle"></i> Add Another Investor</button>
                    <button type="button" id="undo-investor-button"><i class="fas fa-minus-circle"></i> Undo Last Investor</button>

                    <button type="submit"><i class="fas fa-save"></i> <?= $stock_id ? 'Update Stock' : 'Add Stock' ?></button>
                </form>
            </section>
        </main>
    </div>

    <script>
        $(document).ready(function () {
            // Add new investor
            $('#add-investor-button').on('click', function () {
                const newInvestor = `
                    <div class="investor-entry">
                        <label>Investor Name:</label>
                        <input type="text" name="investor_name[]" placeholder="Enter investor's name" required>
                        <label>Amount Invested (Rs.):</label>
                        <input type="number" step="0.01" name="amount_invested[]" placeholder="Enter amount invested" required>
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
