<?php
session_start();
include_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST['username']);
    $email = htmlspecialchars($_POST['email']);
    $password = htmlspecialchars($_POST['password']);
    $password_confirm = htmlspecialchars($_POST['password_confirm']);

    if ($password === $password_confirm) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username OR email = :email");
            $stmt->execute([':username' => $username, ':email' => $email]);
            $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingAdmin) {
                $error = "Username or Email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("INSERT INTO admin (username, password, email) VALUES (:username, :password, :email)");
                $stmt->execute([':username' => $username, ':password' => $hashed_password, ':email' => $email]);

                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Passwords do not match.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="register-body">
    <div class="register-container">
        <h1 class="register-title">Admin Registration</h1>
        <?php if (isset($error)) echo "<p class='register-error'>$error</p>"; ?>
        <form method="POST" class="register-form">
            <div class="form-group">
                <label for="username" class="register-label">Username</label>
                <input type="text" id="username" name="username" class="register-input" required>
            </div>

            <div class="form-group">
                <label for="email" class="register-label">Email</label>
                <input type="email" id="email" name="email" class="register-input" required>
            </div>

            <div class="form-group">
                <label for="password" class="register-label">Password</label>
                <input type="password" id="password" name="password" class="register-input" required>
            </div>

            <div class="form-group">
                <label for="password_confirm" class="register-label">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" class="register-input" required>
            </div>

            <button type="submit" class="register-btn">Register</button>
        </form>
    </div>
</body>
</html>
