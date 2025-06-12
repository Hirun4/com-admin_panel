<?php
session_start();
include_once '../config/config.php';

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: ../dashboard/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            header("Location: ../dashboard/index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
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
        <h1 class="login-title">Admin Login</h1>
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

            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>
</body>

</html>