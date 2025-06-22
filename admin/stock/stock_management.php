<?php
session_start();
include_once '../config/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Handle Delete action (move stock to recycle bin)
if (isset($_GET['delete_stock_id'])) {
    $stock_id = intval($_GET['delete_stock_id']);

    try {
        $stmt = $pdo->prepare("UPDATE stock SET deleted_at = NOW() WHERE id = :stock_id");
        $stmt->bindParam(':stock_id', $stock_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['message'] = "Stock moved to recycle bin successfully.";
        header("Location: stock_management.php");
        exit;
    } catch (PDOException $e) {
        die("SQL Error: " . $e->getMessage());
    }
}

// Handle form submission for adding or updating stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock_name = $_POST['stock_name'] ?? null;
    $stock_number = $_POST['stock_number'] ?? null;
    $purchase_date = $_POST['purchase_date'] ?? null;
    $amount_purchased = $_POST['amount_purchased'] ?? null;
    $purchase_price = $_POST['purchase_price'] ?? null;
    $selling_price = $_POST['selling_price'] ?? null;
    $investor_names = $_POST['investor_name'] ?? [];
    $amounts_invested = $_POST['amount_invested'] ?? [];

    // Prepare investor details as JSON
    $investor_details = [];
    foreach ($investor_names as $index => $investor_name) {
        $amount_invested = $amounts_invested[$index] ?? 0;
        if (!empty($investor_name) && $amount_invested > 0) {
            $investor_details[] = [
                'name' => $investor_name,
                'amount' => $amount_invested,
            ];
        }
    }
    $investor_details_json = json_encode($investor_details);

    try {
        // Check if the stock already exists by stock_number
        $stmt = $pdo->prepare("SELECT id, investor_details FROM stock WHERE stock_number = ?");
        $stmt->execute([$stock_number]);
        $existingStock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingStock) {
            // Insert a new stock if it doesn't exist
            $stmt = $pdo->prepare("
                INSERT INTO stock (stock_name, stock_number, purchase_date, amount_purchased, purchase_price, selling_price, investor_details) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$stock_name, $stock_number, $purchase_date, $amount_purchased, $purchase_price, $selling_price, $investor_details_json]);
        } else {
            // Merge existing investors with new ones
            $existing_investors = json_decode($existingStock['investor_details'], true) ?: [];
            $updated_investors = array_merge($existing_investors, $investor_details);

            $updated_investors_json = json_encode($updated_investors);

            // Update the existing stock
            $stmt = $pdo->prepare("
                UPDATE stock 
                SET 
                    amount_purchased = amount_purchased + ?, 
                    purchase_price = ?, 
                    selling_price = ?, 
                    investor_details = ? 
                WHERE stock_number = ?
            ");
            $stmt->execute([$amount_purchased, $purchase_price, $selling_price, $updated_investors_json, $stock_number]);
        }

        $_SESSION['message'] = 'Stock and investor details added successfully!';
        header("Location: stock_management.php");
        exit;
    } catch (PDOException $e) {
        echo "SQL Error: " . $e->getMessage();
        exit;
    }
}

// Fetch stock data
try {
    $query = "
        SELECT 
            id AS stock_id,
            stock_number,
            stock_name,
            purchase_date,
            amount_purchased,
            purchase_price,
            selling_price,
            investor_details,
            (amount_purchased * (selling_price - purchase_price)) AS profit
        FROM stock
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management</title>
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
        .add-stock, .stock-table {
            margin-bottom: 2rem;
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
        .scrollable-table {
            overflow-x: auto;
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
        th, td {
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
        .actions a {
            margin-right: 0.5rem;
        }
        .btn-outline-primary, .btn-outline-danger {
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
        @media (max-width: 1200px) {
            table { min-width: 700px; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 1rem; }
            .title { font-size: 1.2rem; }
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
                <i class="fas fa-cogs"></i> Stock Management
                <a href="recycle_bin.php" class="btn btn-outline-danger btn-sm ms-auto"><i class="fas fa-trash-alt"></i> Recycle Bin</a>
            </div>
            <div class="card p-4 mb-4">
                <h4 class="mb-3"><i class="fas fa-plus"></i> Add or Update Stock</h4>
                <?php if (isset($_SESSION['message'])): ?>
                    <p class="success"><?= htmlspecialchars($_SESSION['message']) ?></p>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <p class="error"><?= htmlspecialchars($_SESSION['error']) ?></p>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="stock_name" class="form-label"><i class="fas fa-box"></i> Stock Name:</label>
                        <input type="text" id="stock_name" name="stock_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="stock_number" class="form-label"><i class="fas fa-barcode"></i> Stock Number:</label>
                        <input type="text" id="stock_number" name="stock_number" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="purchase_date" class="form-label"><i class="fas fa-calendar-alt"></i> Purchase Date:</label>
                        <input type="date" id="purchase_date" name="purchase_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="amount_purchased" class="form-label"><i class="fas fa-boxes"></i> Amount Purchased:</label>
                        <input type="number" id="amount_purchased" name="amount_purchased" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="purchase_price" class="form-label"><i class="fas fa-dollar-sign"></i> Purchase Price (per unit) (Rs.):</label>
                        <input type="number" step="0.01" id="purchase_price" name="purchase_price" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="selling_price" class="form-label"><i class="fas fa-tag"></i> Selling Price (per unit) (Rs.):</label>
                        <input type="number" step="0.01" id="selling_price" name="selling_price" class="form-control" required>
                    </div>

                    <h3 class="mb-3"><i class="fas fa-users"></i> Investor Details</h3>
                    <div id="investors-container">
                        <div class="investor-entry">
                            <label for="investor_name"><i class="fas fa-user"></i> Investor Name:</label>
                            <input type="text" name="investor_name[]" class="form-control" required>

                            <label for="amount_invested"><i class="fas fa-money-bill-wave"></i> Amount Invested (Rs.):</label>
                            <input type="number" step="0.01" name="amount_invested[]" class="form-control" required>
                        </div>
                    </div>
                    <button type="button" id="add-investor-button" class="btn btn-outline-primary"><i class="fas fa-plus-circle"></i> Add Another Investor</button>
                    <button type="button" id="remove-investor-button" class="btn btn-outline-danger"><i class="fas fa-minus-circle"></i> Undo Last Investor</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add/Update Stock</button>
                </form>
            </div>
            <div class="card p-4">
                <h4 class="mb-3"><i class="fas fa-table"></i> Stock Details</h4>
                <div class="scrollable-table">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Stock Number</th>
                                <th>Stock Name</th>
                                <th>Purchase Date</th>
                                <th>Amount Purchased</th>
                                <th>Purchase Price (Rs.)</th>
                                <th>Selling Price (Rs.)</th>
                                <th>Profit (Rs.)</th>
                                <th>Investors</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stocks as $stock): ?>
                                <tr>
                                    <td><?= htmlspecialchars($stock['stock_number']) ?></td>
                                    <td><?= htmlspecialchars($stock['stock_name']) ?></td>
                                    <td><?= htmlspecialchars($stock['purchase_date']) ?></td>
                                    <td><?= htmlspecialchars($stock['amount_purchased']) ?></td>
                                    <td>Rs. <?= number_format($stock['purchase_price'], 2) ?></td>
                                    <td>Rs. <?= number_format($stock['selling_price'], 2) ?></td>
                                    <td>Rs. <?= number_format($stock['profit'], 2) ?></td>
                                    <td>
                                        <?php
                                        $investors = !empty($stock['investor_details']) ? json_decode($stock['investor_details'], true) : [];
                                        if ($investors) {
                                            foreach ($investors as $investor) {
                                                echo htmlspecialchars($investor['name']) . ": Rs. " . number_format($investor['amount'], 2) . "<br>";
                                            }
                                        } else {
                                            echo 'No Investors';
                                        }
                                        ?>
                                    </td>
                                    <td class="actions">
                                        <a class="edit" href="add_stock.php?stock_id=<?= htmlspecialchars($stock['stock_id']) ?>"><i class="fas fa-edit"></i> Edit</a>
                                        <a class="delete" href="stock_management.php?delete_stock_id=<?= htmlspecialchars($stock['stock_id']) ?>" onclick="return confirm('Are you sure?');"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('add-investor-button').addEventListener('click', function() {
            const container = document.getElementById('investors-container');
            const newEntry = document.createElement('div');
            newEntry.classList.add('investor-entry');
            newEntry.innerHTML = `
            <label>Investor Name:</label>
            <input type="text" name="investor_name[]" class="form-control" required>
            <label>Amount Invested (Rs.):</label>
            <input type="number" step="0.01" name="amount_invested[]" class="form-control" required>
        `;
            container.appendChild(newEntry);
        });

        document.getElementById('remove-investor-button').addEventListener('click', function() {
            const container = document.getElementById('investors-container');
            const entries = container.getElementsByClassName('investor-entry');

            if (entries.length > 1) {
                container.removeChild(entries[entries.length - 1]);
            } else {
                alert('At least one investor entry must remain.');
            }
        });
    </script>
</body>
</html>