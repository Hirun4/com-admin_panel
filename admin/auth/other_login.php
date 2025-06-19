<?php

session_start();
include_once '../config/config.php';
// $_SESSION['admin_logged_in'] = true;

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: ../dashboard/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check other admins (plain password)
    $stmt = $pdo->prepare("SELECT * FROM new_admins WHERE name = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $admin1 = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r("admin");
    if ($admin1) {
        $_SESSION['username'] = $username;
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['is_other_admin'] = true;
        $_SESSION['admin_id'] = $admin1['id'];
        // print_r($_SESSION);
        // exit;

        header('Location: ../dashboard/index.php');
        exit;
    } else {
        // header('Location: login.php?error=1');
        // exit;
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body class="login-body">
    <div class="login-container">
        <h1 class="login-title">Other Admin Login</h1>
        <?php if (isset($error)) echo "<p class='login-error'>$error</p>"; ?>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username" class="login-label">Username</label>
                <input type="text" id="username" name="username" class="login-input" required>
            </div>

            <div class="form-group">
                <label for="password" class="login-label">Password</label>
                <input type="password" id="password" name="password" class="login-input" required>
            </div>

            <span>If you Main Admin<a href="login.php">click here</a></span>

            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>
</body>

</html>