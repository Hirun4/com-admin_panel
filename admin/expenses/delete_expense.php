<?php
session_start();

// Include the database configuration
if (file_exists('../config/config.php')) {
    include_once '../config/config.php';
} else {
    die("Configuration file not found. Please check the path to config.php.");
}

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../../auth/login.php");
    exit;
}

// Check if expense_id is provided
if (!isset($_GET['expense_id'])) {
    header("Location: manage_expenses.php");
    exit;
}

$expense_id = intval($_GET['expense_id']);

try {
    // Prepare and execute delete query
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :expense_id");
    $stmt->execute([':expense_id' => $expense_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = 'Expense deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Expense not found or could not be deleted.';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

// Redirect back to manage expenses page
header("Location: manage_expenses.php");
exit;
?>
