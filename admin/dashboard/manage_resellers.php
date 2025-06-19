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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sellers</title>
    <link rel="stylesheet" href="../assets/css/manage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- jQuery for AJAX -->
</head>

<body>
    <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <h1><i class="fas fa-users"></i> Manage Sellers</h1>

        <!-- 🔍 Search Box -->
        <input type="text" id="search" placeholder="Search by seller name..." class="search-box">

        <!-- 📌 Seller List (Dynamically Updated via AJAX) -->
        <div id="seller-list">
            <!-- Sellers will be loaded here via AJAX -->
        </div>
    </div>

    <script>
        $(document).ready(function() {
            function fetchSellers(query = '') {
                $.ajax({
                    url: "fetch_sellers.php", // Fetch filtered sellers
                    method: "POST",
                    data: {
                        search: query
                    },
                    success: function(data) {
                        $("#seller-list").html(data); // Update seller list dynamically
                        console.log(data);
                    }
                });
            }
            
            fetchSellers(); // Load all sellers initially

            $("#search").on("keyup", function() {
                let query = $(this).val();
                fetchSellers(query); // Fetch filtered results as user types
            });
        });
    </script>
    </div>
</body>

</html>