<?php
session_start();

if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found.");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $account_details = $_POST['account_details'] ?? '';
    $contact = $_POST['contactno'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? '';
    $profit = $_POST['profit'] ?? '';
    $quantities = $_POST['quantities'] ?? [];

    if (true) {
        try {
            $pdo->beginTransaction();

            // Prepare size_quantity data
            $size_quantity = [];
            foreach ($quantities as $size => $quantity) {
                $size_quantity[] = "$size-$quantity";
            }
            $size_quantity_str = implode(',', $size_quantity);

            $stmt = $pdo->prepare("
                INSERT INTO reseller (name, account_details, contact_No, category, price, profit, size_quantity) 
                VALUES (:name, :account_details, :contact_No, :category, :price, :profit, :size_quantity)
            ");
            $stmt->execute([
                ':name' => $name,
                ':account_details' => $account_details,
                ':contact_No' => $contact,
                ':category' => $category,
                ':price' => $price,
                ':profit' => $profit,
                ':size_quantity' => $size_quantity_str,
            ]);

            $pdo->commit();
            $success = 'Reseller added successfully!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

if (empty($error)) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, name FROM products");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT product_id, size, quantity FROM product_stock");
        $stmt->execute();
        $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Reseller</title>
    <link rel="stylesheet" href="../assets/css/add_product.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="../dashboard/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>
                <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="../dashboard/monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="../dashboard/resellers.php" class="active"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        <div class="main-content">
            <div class="title">
                <h1><i class="fas fa-plus-circle"></i> Add New Reseller</h1>
                <a href="../dashboard/manage_resellers.php"><i class="fas fa-boxes"></i> Manage Resellers</a>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="name"><i class="fas fa-box"></i> Reseller Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter reseller name" required>
                </div>
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Account Details</label>
                    <textarea id="description" name="account_details" rows="4" placeholder="Enter account details" required></textarea>
                </div>
                <div class="form-group">
                    <label for="contactno"><i class="fas fa-tag"></i> Contact No</label>
                    <input type="number" id="contactno" name="contactno" placeholder="Enter Contact No" required>
                </div>
                <div class="form-group">
                    <label for="category"><i class="fas fa-list-alt"></i> Resale Products</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= htmlspecialchars($product['product_id']) ?>"><?= htmlspecialchars($product['name']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No products found.</option>
                        <?php endif; ?>
                    </select>
                </div>
                <!-- <div class="form-group">
                <label for="quantities"><i class="fas fa-box-open"></i> Stock (Size and Quantity)</label>
                <div id="stock-container"></div>
            </div> -->
                <div class="form-group">
                    <label for="price"><i class="fas fa-tag"></i> Resale Price</label>
                    <input type="number" id="price" name="price" placeholder="Enter Price" required>
                </div>
                <div class="form-group">
                    <label for="profit"><i class="fas fa-tag"></i> Resale Profit</label>
                    <input type="number" id="profit" name="profit" placeholder="Enter Profit" required>
                </div>
                <button type="submit"><i class="fas fa-save"></i> Add Reseller</button>
            </form>
        </div>
    </div>
    <script>
        // Embed the product and stock data as JSON objects in the HTML
        const products = <?= json_encode($products) ?>;
        const stock = <?= json_encode($stock) ?>;

        function updateSizes() {
            const category = document.getElementById("category").value;
            const sizesContainer = document.getElementById("stock-container");
            sizesContainer.innerHTML = "";

            let sizeOptions = [];
            // Find the stock data for the selected product
            const selectedStock = stock.filter(item => item.product_id == category);
            if (selectedStock.length > 0) {
                sizeOptions = selectedStock.map(item => ({
                    size: item.size,
                    maxQuantity: item.quantity
                }));
            }

            sizeOptions.forEach(option => {
                const div = document.createElement("div");
                div.className = "size-group";

                const label = document.createElement("label");
                label.textContent = `Size ${option.size}:`;

                const input = document.createElement("input");
                input.type = "number";
                input.name = `quantities[${option.size}]`;
                input.placeholder = "Enter stock quantity";
                input.min = 0; // Set minimum quantity to 0
                input.max = option.maxQuantity;

                div.appendChild(label);
                div.appendChild(input);
                sizesContainer.appendChild(div);
            });
        }
    </script>
</body>

</html>