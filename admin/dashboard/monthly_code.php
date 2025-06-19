<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';

    if ($code === '3970') {
        header("Location: monthly_report.php");
        exit;
    } else {
        $error = 'Invalid Code!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Code</title>
    <link rel="stylesheet" href="../assets/css/code.css">
    <!-- Font Awesome CDN link -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <div class="main-content">
            <div class="content">
                <div class="top-bar">Enter Code</div>
                <form method="POST">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <input type="text" id="code" name="code" placeholder="Enter Access Code" required>
                    </div>

                    <button type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>

    <!-- <script>
        // Highlight active link in the sidebar
        const currentPage = window.location.pathname.split('/').pop(); // Get the current page
        const sidebarLinks = document.querySelectorAll('.sidebar nav a');

        sidebarLinks.forEach(link => {
            if (link.href.includes(currentPage)) {
                link.classList.add('active'); // Add 'active' class to the link of the current page
            }
        });
    </script> -->
</body>

</html>