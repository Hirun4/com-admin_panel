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

    if ($code && $buying_price) {
        try {
            // Check if the code already exists in the database
            $stmtCheck = $pdo->prepare("SELECT * FROM product_codes WHERE code = :code");
            $stmtCheck->execute([':code' => $code]);
            $existingCode = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingCode) {
                $error = 'This code already exists!';
            } else {
                // Insert the new code and buying price into the product_codes table
                $stmtInsert = $pdo->prepare("INSERT INTO product_codes (code, buying_price) VALUES (:code, :buying_price)");
                $stmtInsert->execute([':code' => $code, ':buying_price' => $buying_price]);
                $success = 'Code added successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please enter a valid code and buying price!';
    }
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
    <div class="sidebar">
        <h1>Admin Panel</h1>
        <nav>
            <a href="../dashboard/index.php" id="dashboard-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_orders.php"><i class="fas fa-box"></i> Manage Orders</a>
            <a href="add_code.php" id="add-code-link" class="active"><i class="fas fa-key"></i> Add Code</a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">Add New Code</div>
        <form method="POST">
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

            <button type="submit">Add Code</button>
        </form>
    </div>

    <script>
        // Highlight active link in the sidebar
        const currentPage = window.location.pathname.split('/').pop(); // Get the current page
        const sidebarLinks = document.querySelectorAll('.sidebar nav a');

        sidebarLinks.forEach(link => {
            if (link.href.includes(currentPage)) {
                link.classList.add('active'); // Add 'active' class to the link of the current page
            }
        });
    </script>
</body>
</html>
