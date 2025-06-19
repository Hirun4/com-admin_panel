<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found. Please check the path to config.php.");
}

include_once '../config/cloudinary_config.php'; // Add this after config.php

require_once __DIR__ . '/../../vendor/autoload.php';
use Cloudinary\Api\Upload\UploadApi;

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$error = '';
$success = '';

// Check if product_id is provided
if (!isset($_GET['product_id'])) {
    header("Location: manage_products.php");
    exit;
}

$product_id = intval($_GET['product_id']);

// Fetch product details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :product_id");
    $stmt->execute([':product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: manage_products.php");
        exit;
    }

    // Fetch stock sizes and quantities
    $stockStmt = $pdo->prepare("SELECT size, quantity FROM product_stock WHERE product_id = :product_id");
    $stockStmt->execute([':product_id' => $product_id]);
    $stockData = $stockStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $category = $_POST['category'] ?? '';
    $sizes = $_POST['sizes'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    // Handle image upload if provided
    $upload_dir = '../assets/productuploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $image_url = $product['image_url'];
    if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] === 0) {
        $image_tmp_name = $_FILES['image_url']['tmp_name'];
        $image_name = $_FILES['image_url']['name'];
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($image_ext, $allowed_exts)) {
            $error = 'Invalid image format. Only JPG, PNG, and GIF are allowed.';
        } else {
            try {
                $upload = (new UploadApi())->upload($image_tmp_name, [
                    'folder' => 'products'
                ]);
                $image_url = $upload['secure_url'];
            } catch (Exception $e) {
                $error = 'Cloudinary upload failed: ' . $e->getMessage();
            }
        }
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            // Update product details
            $stmt = $pdo->prepare(
                "UPDATE products SET name = :name, description = :description, price = :price, image_url = :image_url, category = :category, updated_at = NOW() WHERE product_id = :product_id"
            );
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':image_url' => $image_url,
                ':category' => $category,
                ':product_id' => $product_id,
            ]);

            // Clear existing stock data
            $deleteStmt = $pdo->prepare("DELETE FROM product_stock WHERE product_id = :product_id");
            $deleteStmt->execute([':product_id' => $product_id]);

            // Insert updated sizes and quantities
            $insertStmt = $pdo->prepare("INSERT INTO product_stock (product_id, size, quantity) VALUES (:product_id, :size, :quantity)");
            foreach ($sizes as $index => $size) {
                $insertStmt->execute([
                    ':product_id' => $product_id,
                    ':size' => $size,
                    ':quantity' => $quantities[$index],
                ]);
            }

            $pdo->commit();
            $success = 'Product updated successfully!';
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
    <title>Edit Product</title>
    <link rel="stylesheet" href="../assets/css/edit.css">
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
            <div class="title">
                <h1 class="top-bar">
                    <i class="fas fa-edit"></i> Edit Product
                </h1>
                <a href="../products/manage_products.php" class="active"><i class="fas fa-boxes"></i> Manage Products</a>
            </div>
            <div class="container2">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-tag"></i> Product Name:</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-info-circle"></i> Description:</label>
                        <textarea id="description" name="description" required><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price"><i class="fas fa-dollar-sign"></i> Price (Rs.):</label>
                        <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($product['price']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category"><i class="fas fa-tags"></i> Category:</label>
                        <select id="category" name="category" required>
                            <option value="Ladies" <?= $product['category'] === 'ladies' ? 'selected' : '' ?>>Ladies Shoes</option>
                            <option value="Kids" <?= $product['category'] === 'kids' ? 'selected' : '' ?>>Kids Shoes</option>
                            <option value="Gents" <?= $product['category'] === 'gents' ? 'selected' : '' ?>>Gents Shoes</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-random"></i> Sizes and Quantities:</label>
                        <div id="sizes-container">
                            <?php foreach ($stockData as $stock): ?>
                                <div class="size-group">
                                    <input type="hidden" name="sizes[]" value="<?= htmlspecialchars($stock['size']) ?>">
                                    <label>Size <?= htmlspecialchars($stock['size']) ?>:</label>
                                    <input type="number" name="quantities[]" value="<?= htmlspecialchars($stock['quantity']) ?>" required>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="image_url"><i class="fas fa-image"></i> Product Image:</label>
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="Product Image">
                        <?php endif; ?>
                        <input type="file" id="image_url" name="image_url" accept="image/*">
                    </div>

                    <button type="submit"><i class="fas fa-check-circle"></i> Update Product</button>
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