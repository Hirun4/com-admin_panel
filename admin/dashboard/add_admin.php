<?php
include_once '../config/config.php';

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input
    $admin_name = trim($_POST['admin_name']);
    $address = trim($_POST['address']);
    $phone_number = trim($_POST['phone_number']);
    $password = trim($_POST['password']);

    // Prepare and execute insert statement
    $sql = "INSERT INTO new_admins (name, address, phone_number, password, dashboard, Manage_products, Manage_orders, Stock_Management, Manage_expence, Facebook_ads, Monthly_reports, Resellers) VALUES (:name, :address, :phone_number, :password,  :dashboard, :Manage_products, :Manage_orders, :Stock_Management, :Manage_expence, :Facebook_ads, :Monthly_reports, :Resellers)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':name' => $admin_name,
        ':address' => $address,
        ':phone_number' => $phone_number,
        ':password' => $password,
        ':dashboard' => 'no', // Assuming default permissions
        ':Manage_products' => 'no', // Assuming default permissions  
        ':Manage_orders' => 'no', // Assuming default permissions
        ':Stock_Management' => 'no', // Assuming default permissions
        ':Manage_expence' => 'no', // Assuming default permissions
        ':Facebook_ads' => 'no', // Assuming default permissions
        ':Monthly_reports' => 'no', // Assuming default permissions
        ':Resellers' => 'no' // Assuming default permissions  
    ]);

    if ($result) {
        $message = "Admin added successfully!";
    } else {
        $message = "Error adding admin.";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Admin</title>
</head>

<body>
    <h2>Add Admin</h2>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form action="" method="post">
        <label>Admin Name:</label>
        <input type="text" name="admin_name" required><br><br>

        <label>Address:</label>
        <input type="text" name="address" required><br><br>

        <label>Phone Number:</label>
        <input type="text" name="phone_number" required><br><br>

        <label>Password:</label>
        <input type="text" name="password" required><br><br>

        <button type="submit">Add Admin</button>
    </form>
    <br>
    <a href="manage_admins.php"><i class="fas fa-users-cog"></i> Manage Admin</a>
</body>

</html>