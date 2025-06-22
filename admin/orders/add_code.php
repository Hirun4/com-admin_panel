<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Include the database connection (adjust the path as needed)
include_once '../config/config.php'; // Ensure this file contains the $pdo initialization

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $buying_price = $_POST['buying_price'] ?? 0;
    $co_codes = $_POST['co_codes'] ?? [];

    if ($code && $buying_price && !empty($co_codes)) {
        try {
            // Check if the code already exists in the database
            $stmtCheck = $pdo->prepare("SELECT * FROM product_codes WHERE code = :code");
            $stmtCheck->execute([':code' => $code]);
            $existingCode = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingCode) {
                $error = 'This code already exists!';
            } else {
                // Insert the new code, buying price, and co_codes into the product_codes table
                $stmtInsert = $pdo->prepare("INSERT INTO product_codes (code, buying_price, co_codes) VALUES (:code, :buying_price, :co_codes)");
                $stmtInsert->execute([
                    ':code' => $code,
                    ':buying_price' => $buying_price,
                    ':co_codes' => json_encode($co_codes)
                ]);
                $success = 'Code added successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please enter a valid code, buying price, and at least one co code!';
    }
}

// --- Real-time fetch endpoint for buying price code ---
if (isset($_GET['fetch_buying_price_code']) && isset($_GET['co_code'])) {
    header('Content-Type: application/json');
    $co_code = $_GET['co_code'];
    if (!$co_code) {
        echo json_encode(['success' => false, 'message' => 'No co code provided']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT code FROM product_codes WHERE JSON_CONTAINS(co_codes, :co_code_json)");
    $stmt->execute([':co_code_json' => json_encode($co_code)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'buying_price_code' => $row['code']]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Code</title>
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
        .top-bar {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: #212529;
            text-align: center;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
            background: #fff;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control {
            border-radius: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .btn-primary {
            width: 100%;
            font-size: 1.1rem;
            padding: 0.75rem;
        }
        .co-code-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.5rem;
        }
        .buying-price-code-input {
            width: 180px;
            background: #f1f3f5;
        }
        .add-co-btn {
            background: #17a2b8;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.4rem 1rem;
            margin-top: 5px;
            font-size: 1rem;
        }
        .add-co-btn:hover {
            background: #138496;
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
        <div class="main-content d-flex align-items-center justify-content-center" style="min-height: 100vh;">
            <div class="card p-4" style="max-width: 500px; width: 100%;">
                <div class="top-bar mb-4">Add New Code</div>
                <form method="POST" id="add-code-form">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="code" class="form-label"><i class="fas fa-barcode"></i> Code</label>
                        <input type="text" id="code" name="code" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="buying_price" class="form-label"><i class="fas fa-dollar-sign"></i> Buying Price</label>
                        <input type="number" id="buying_price" name="buying_price" step="0.01" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-code"></i> Co Codes</label>
                        <div id="co-codes-wrapper">
                            <div class="co-code-group">
                                <input type="text" name="co_codes[]" class="form-control co-code-input" placeholder="Co Code" required>
                                <input type="text" class="form-control buying-price-code-input" placeholder="Buying Price Code" readonly>
                            </div>
                        </div>
                        <button type="button" class="add-co-btn" onclick="addCoCodeField()"><i class="fas fa-plus"></i> Add Co Code</button>
                    </div>

                    <button type="submit" class="btn btn-primary mt-2"><i class="fas fa-plus"></i> Add Code</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight active link in the sidebar
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.sidebar nav a');
        sidebarLinks.forEach(link => {
            if (link.href.includes(currentPage)) {
                link.classList.add('active');
            }
        });

        function addCoCodeField() {
            var wrapper = document.getElementById('co-codes-wrapper');
            var group = document.createElement('div');
            group.className = 'co-code-group';
            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'co_codes[]';
            input.className = 'form-control co-code-input';
            input.placeholder = 'Co Code';
            input.required = true;

            var buyingPriceInput = document.createElement('input');
            buyingPriceInput.type = 'text';
            buyingPriceInput.className = 'form-control buying-price-code-input';
            buyingPriceInput.placeholder = 'Buying Price Code';
            buyingPriceInput.readOnly = true;

            group.appendChild(input);
            group.appendChild(buyingPriceInput);
            wrapper.appendChild(group);

            input.addEventListener('blur', handleCoCodeInput);
        }

        // Attach event listeners to existing co code input
        document.querySelectorAll('.co-code-input').forEach(input => {
            input.addEventListener('blur', handleCoCodeInput);
        });

        function handleCoCodeInput(e) {
            const coCode = e.target.value.trim();
            const buyingPriceInput = e.target.parentElement.querySelector('.buying-price-code-input');
            if (!coCode) {
                buyingPriceInput.value = '';
                return;
            }
            fetch(`add_code.php?fetch_buying_price_code=1&co_code=${encodeURIComponent(coCode)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        buyingPriceInput.value = data.buying_price_code;
                    } else {
                        buyingPriceInput.value = 'Invalid';
                    }
                })
                .catch(() => {
                    buyingPriceInput.value = 'Error';
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.co-code-input').forEach(input => {
                input.addEventListener('blur', handleCoCodeInput);
            });
        });
    </script>
</body>
</html>
