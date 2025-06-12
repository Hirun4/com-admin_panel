<?php
session_start();

if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found.");
}

if (!isset($_SESSION['admin_logged_in'])) {
    die("Unauthorized access.");
}

$searchQuery = isset($_POST['search']) ? trim($_POST['search']) : '';

try {
    // Fetch resellers with search filter
    $stmt = $pdo->prepare("
        SELECT r.*, p.name AS product_name 
        FROM reseller r
        LEFT JOIN products p ON r.category = p.product_id
        WHERE r.name LIKE :search
    ");
    $stmt->execute([':search' => "%$searchQuery%"]);
    $resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($resellers)) {
        foreach ($resellers as $reseller) {
            echo '
            <form action="update_reseller.php" method="POST" class="reseller-form">
                <input type="hidden" name="reseller_id" value="' . htmlspecialchars($reseller['reseller_id']) . '">
                
                <div class="form-group">
                    <label for="name"><i class="fas fa-user"></i> Reseller Name</label>
                    <input type="text" id="name" name="name" value="' . htmlspecialchars($reseller['name']) . '" required>
                </div>

                <div class="form-group">
                    <label for="account_details"><i class="fas fa-info-circle"></i> Account Details</label>
                    <textarea id="account_details" name="account_details" rows="3" required>' . htmlspecialchars($reseller['account_details']) . '</textarea>
                </div>

                <div class="form-group">
                    <label for="contact_no"><i class="fas fa-phone"></i> Contact No</label>
                    <input type="text" id="contact_no" name="contact_no" value="' . htmlspecialchars($reseller['contact_No']) . '" required>
                </div>

                <div class="form-group">
                    <label for="product_name"><i class="fas fa-box"></i> Product Name</label>
                    <input type="text" id="product_name" name="product_name" value="' . htmlspecialchars($reseller['product_name']) . '" required readonly>
                </div>

                <div class="form-group">
                    <label for="price"><i class="fas fa-dollar-sign"></i> Price</label>
                    <input type="text" id="price" name="price" value="' . htmlspecialchars($reseller['price']) . '" required>
                </div>

                <div class="form-group">
                    <label for="profit"><i class="fas fa-chart-line"></i> Profit</label>
                    <input type="text" id="profit" name="profit" value="' . htmlspecialchars($reseller['profit']) . '" required>
                </div>

                <button type="submit" class="btn btn-primary">Update</button>
                <form action="delete_reseller.php" method="POST" >
                    <input type="hidden" name="reseller_id" value="' . htmlspecialchars($reseller['reseller_id']) . '">
                    <button type="submit" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete this reseller?\');">Delete</button>
                </form>
            </form>

            <hr>';
        }
    } else {
        echo "<p>No resellers found.</p>";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
