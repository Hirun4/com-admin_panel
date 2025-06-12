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

    if ($reseller_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM reseller WHERE reseller_id = :reseller_id");
            $stmt->execute([':reseller_id' => $reseller_id]);

            // Redirect back to the manage resellers page
            header("Location: manage_resellers.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    } else {
        die("Error: Missing reseller ID.");
    }
} else {
    die("Error: Invalid request method.");
}
?>