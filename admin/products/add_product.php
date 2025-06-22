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

include_once '../config/cloudinary_config.php'; // Add this after config.php

require_once __DIR__ . '/../../vendor/autoload.php';

use Cloudinary\Api\Upload\UploadApi;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0.00;
    $category = $_POST['category'] ?? '';
    $origin_country = $_POST['origin_country'] ?? '';
    $quantities = $_POST['quantities'] ?? [];
    $buying_price_code = $_POST['buying_price_code'] ?? '';

    $upload_dir = '../assets/productuploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $image_url = '';
    if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] === 0) {
        $image_tmp_name = $_FILES['image_url']['tmp_name'];
        $image_name = $_FILES['image_url']['name'];
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        if (in_array($image_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            try {
                $upload = (new UploadApi())->upload($image_tmp_name, [
                    'folder' => 'products'
                ]);
                $image_url = $upload['secure_url'];
            } catch (Exception $e) {
                $error = 'Cloudinary upload failed: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid image format.';
        }
    }

    $other_images_urls = [];

    if (isset($_FILES['other_images']) && count($_FILES['other_images']['name']) > 0) {
        $files = $_FILES['other_images'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === 0) {
                $tmp_name = $files['tmp_name'][$i];
                $photoname = $files['name'][$i];
                $ext = strtolower(pathinfo($photoname, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    try {
                        $upload = (new UploadApi())->upload($tmp_name, [
                            'folder' => 'products'
                        ]);
                        $other_images_urls[] = $upload['secure_url'];
                    } catch (Exception $e) {
                        // Optionally handle upload error
                    }
                }
            }
        }
    }

    // Store as JSON in DB
    $other_images_json = json_encode($other_images_urls);

    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, image_url, category, origin_country, buying_price_code, other_images, created_at, updated_at) 
                VALUES (:name, :description, :price, :image_url, :category, :origin_country, :buying_price_code, :other_images, NOW(), NOW())
            ");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':image_url' => $image_url,
                ':category' => $category,
                ':origin_country' => $origin_country,
                ':buying_price_code' => $buying_price_code,
                ':other_images' => $other_images_json,
            ]);

            $product_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO product_stock (product_id, size, quantity) 
                VALUES (:product_id, :size, :quantity )
            ");
            foreach ($quantities as $size => $quantity) {
                $stmt->execute([
                    ':product_id' => $product_id,
                    ':size' => $size,
                    ':quantity' => $quantity

                ]);
            }

            $pdo->commit();
            $success = 'Product added successfully!';
        } catch (PDOException $e) {
            $pdo->rollBack();
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
    <title>Add Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6fb;
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

        .sidebar nav a.active,
        .sidebar nav a:hover {
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
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
        }

        .alert {
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #f8fafc;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, .15);
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .btn-primary,
        .btn-outline-primary {
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

        .size-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.5rem;
        }

        .size-group label {
            min-width: 80px;
            margin-bottom: 0;
        }

        .alert-error {
            background: #ffeaea;
            color: #b71c1c;
            border: 1px solid #f5c6cb;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #c3e6cb;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
        }

        @media (max-width: 991px) {
            .main-content {
                padding: 1rem;
            }

            .title {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                min-width: 100px;
            }

            .main-content {
                padding: 0.5rem;
            }

            .card {
                padding: 1rem;
            }
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
        <div class="main-content">
            <div class="title">
                <i class="fas fa-plus-circle"></i> Add New Product
                <div class="ms-auto">
                    <a href="P_P.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-key"></i> Enter Code</a>
                    <a href="manage_products.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-boxes"></i> Manage Products</a>
                </div>
            </div>
            <div class="card p-4">
                <form action="" method="POST" enctype="multipart/form-data">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="buying_price_code" class="form-label"><i class="fas fa-dollar-sign"></i> Buying Price (Code)</label>
                        <input type="text" id="buying_price_code" name="buying_price_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="name" class="form-label"><i class="fas fa-box"></i> Product Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="Enter product name" required>
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Enter product description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="price" class="form-label"><i class="fas fa-tag"></i> Price (Rs.)</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" placeholder="Enter price" required>
                    </div>
                    <div class="form-group">
                        <label for="origin_country" class="form-label"><i class="fas fa-flag"></i> Origin Country</label>
                        <select id="origin_country" name="origin_country" class="form-select" required>
                            <option value="">Select Country</option>
                            <option value="Local">Local</option>
                            <option value="Vietnam">Vietnam</option>
                            <option value="China">China</option>
                            <option value="India">India</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category" class="form-label"><i class="fas fa-list-alt"></i> Category</label>
                        <select id="category" name="category" class="form-select" required onchange="updateSizes()">
                            <option value="">Select Category</option>
                            <option value="Gents">Gents</option>
                            <option value="Ladies">Ladies</option>
                            <option value="Kids">Kids</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantities" class="form-label"><i class="fas fa-box-open"></i> Stock (Size and Quantity)</label>
                        <div id="stock-container"></div>
                    </div>
                    <div class="form-group">
                        <label for="image_url" class="form-label"><i class="fas fa-image"></i> Product Image</label>
                        <input type="file" id="image_url" name="image_url" class="form-control" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label for="image_url" class="form-label"><i class="fas fa-image"></i> Other Image</label>
                        <input type="file" id="other_images" name="other_images[]" class="form-control" accept="image/*" required multiple>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Product</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateSizes() {
            const category = document.getElementById("category").value;
            const sizesContainer = document.getElementById("stock-container");
            sizesContainer.innerHTML = "";

            let sizeOptions = [];
            if (category === "Gents") {
                sizeOptions = [40, 41, 42, 43, 44, 45, 46, 47, 48];
            } else if (category === "Ladies") {
                sizeOptions = [36, 37, 38, 39, 40, 41];
            } else if (category === "Kids") {
                sizeOptions = [31, 32, 33, 34, 35, 36];
            }

            sizeOptions.forEach(size => {
                const div = document.createElement("div");
                div.className = "size-group";

                const label = document.createElement("label");
                label.textContent = `Size ${size}:`;

                const input = document.createElement("input");
                input.type = "number";
                input.name = `quantities[${size}]`;
                input.class = "form-control";
                input.placeholder = "Enter stock quantity";
                input.min = 0; // Set minimum quantity to 0

                div.appendChild(label);
                div.appendChild(input);
                sizesContainer.appendChild(div);
            });
        }

        function enterCode() {
            const code = document.getElementById('buying_price_code').value;
            if (code) {
                fetch(`fetch_buying_price.php?code=${code}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('buying_price').value = data.buying_price;
                        } else {
                            alert('Invalid code!');
                        }
                    });
            }
        }

        function fetchBuyingPrice() {
            const code = document.getElementById('buying_price_code').value;
            if (code) {
                fetch(`fetch_buying_price.php?code=${code}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('buying_price').value = data.buying_price;
                        } else {
                            alert('Invalid code!');
                        }
                    });
            }
        }
    </script>
</body>

</html>