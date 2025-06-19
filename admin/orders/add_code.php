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
    <link rel="stylesheet" href="../assets/css/code.css">
    <!-- Font Awesome CDN link -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
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

    <div class="main-content">
        <div class="top-bar">Add New Code</div>
        <form method="POST" id="add-code-form">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="code">Code:</label>
                <input type="text" id="code" name="code" required>
            </div>

            <div class="form-group">
                <label for="buying_price">Buying Price:</label>
                <input type="number" id="buying_price" name="buying_price" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Co Codes:</label>
                <div id="co-codes-wrapper">
                    <div class="co-code-group">
                        <input type="text" name="co_codes[]" class="co-code-input" required>
                        <input type="text" class="buying-price-code-input" placeholder="Buying Price Code" readonly style="margin-left:10px;">
                    </div>
                </div>
                <button type="button" onclick="addCoCodeField()" style="margin-top:5px;">+</button>
            </div>

            <button type="submit">Add Code</button>
        </form>
    </div>

    <script>
        // Highlight active link in the sidebar
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.sidebar nav a');
        sidebarLinks.forEach(link => {
            if (link.href.includes(currentPage)) {
                link.classList.add('active');
            }
        });

        // Add new co code field with buying price code field
        function addCoCodeField() {
            var wrapper = document.getElementById('co-codes-wrapper');
            var group = document.createElement('div');
            group.className = 'co-code-group';
            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'co_codes[]';
            input.className = 'co-code-input';
            input.required = true;
            input.style.marginTop = '5px';

            var buyingPriceInput = document.createElement('input');
            buyingPriceInput.type = 'text';
            buyingPriceInput.className = 'buying-price-code-input';
            buyingPriceInput.placeholder = 'Buying Price Code';
            buyingPriceInput.readOnly = true;
            buyingPriceInput.style.marginLeft = '10px';

            group.appendChild(input);
            group.appendChild(buyingPriceInput);
            wrapper.appendChild(group);

            input.addEventListener('blur', handleCoCodeInput);
        }

        // Attach event listeners to existing co code input
        document.querySelectorAll('.co-code-input').forEach(input => {
            input.addEventListener('blur', handleCoCodeInput);
        });

        // Handle co code input blur event
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

        // When form is loaded, attach event listeners to all co code inputs
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.co-code-input').forEach(input => {
                input.addEventListener('blur', handleCoCodeInput);
            });
        });
    </script>
</body>
</html>
