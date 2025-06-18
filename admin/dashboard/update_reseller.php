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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reseller_id = $_POST['reseller_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $account_details = $_POST['account_details'] ?? '';
    $contact_no = $_POST['contact_no'] ?? '';
    $price = $_POST['price'] ?? '';
    $profit = $_POST['profit'] ?? '';
    $co_codes = $_POST['co_codes'] ?? [];
    $co_codes_json = json_encode($co_codes);

    if ($reseller_id && $name && $account_details && $contact_no && $price && $profit) {
        try {
            $stmt = $pdo->prepare("
                UPDATE reseller
                SET name = :name, account_details = :account_details, contact_No = :contact_no, price = :price, profit = :profit, co_code = :co_code
                WHERE reseller_id = :reseller_id
            ");
            $stmt->execute([
                ':name' => $name,
                ':account_details' => $account_details,
                ':contact_no' => $contact_no,
                ':price' => $price,
                ':profit' => $profit,
                ':co_code' => $co_codes_json,
                ':reseller_id' => $reseller_id,
            ]);

            // Redirect back to the manage resellers page
            header("Location: manage_resellers.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    } else {
        die("Error: Missing required fields.");
    }
} else {
    die("Error: Invalid request method.");
}
?>