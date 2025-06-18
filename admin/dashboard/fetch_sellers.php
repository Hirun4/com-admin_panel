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

    // Fetch products for the category dropdown
    $productStmt = $pdo->prepare("SELECT product_id, name FROM products");
    $productStmt->execute();
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($resellers)) {
        foreach ($resellers as $reseller) {
            // Decode co_codes for editing
            $co_codes = json_decode($reseller['co_code'] ?? '[]', true);
            if (!is_array($co_codes)) $co_codes = [];

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
                    <label for="price"><i class="fas fa-dollar-sign"></i> Price</label>
                    <input type="text" id="price" name="price" value="' . htmlspecialchars($reseller['price']) . '" required>
                </div>

                <div class="form-group">
                    <label for="profit"><i class="fas fa-chart-line"></i> Profit</label>
                    <input type="text" id="profit" name="profit" value="' . htmlspecialchars($reseller['profit']) . '" required>
                </div>

                <div class="form-group">
                    <label>Co Codes</label>
                    <div id="co-codes-wrapper-' . $reseller['reseller_id'] . '">';
                        if (!empty($co_codes)) {
                            foreach ($co_codes as $code) {
                                echo '<div class="co-code-group">
                                    <input type="text" name="co_codes[]" class="co-code-input" value="' . htmlspecialchars($code) . '" required>
                                    <button type="button" class="remove-co-code-btn" onclick="this.parentNode.remove();">-</button>
                                </div>';
                            }
                        } else {
                            echo '<div class="co-code-group">
                                <input type="text" name="co_codes[]" class="co-code-input" required>
                                <button type="button" class="remove-co-code-btn" onclick="this.parentNode.remove();">-</button>
                            </div>';
                        }
            echo    '</div>
                    <button type="button" onclick="addCoCodeField(\'co-codes-wrapper-' . $reseller['reseller_id'] . '\')">Add Co Code</button>
                </div>

                <button type="submit" class="btn btn-primary">Update</button>
            </form>
            <form action="delete_reseller.php" method="POST" style="display:inline;">
                <input type="hidden" name="reseller_id" value="' . htmlspecialchars($reseller['reseller_id']) . '">
                <button type="submit" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete this reseller?\');">Delete</button>
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
<script>
function addCoCodeField(wrapperId) {
    var wrapper = document.getElementById(wrapperId);
    var group = document.createElement('div');
    group.className = 'co-code-group';
    var input = document.createElement('input');
    input.type = 'text';
    input.name = 'co_codes[]';
    input.className = 'co-code-input';
    input.required = true;
    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '-';
    removeBtn.onclick = function() { group.remove(); };
    group.appendChild(input);
    group.appendChild(removeBtn);
    wrapper.appendChild(group);
}
</script>
