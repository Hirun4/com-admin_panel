<?php
include_once '../config/config.php';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = intval($_POST['update_id']);

    // For each tab, set 'yes' if checked, 'no' if not
    $dashboard = isset($_POST['dashboard']) ? 'yes' : 'no';
    $Manage_products = isset($_POST['Manage_products']) ? 'yes' : 'no';
    $Manage_orders = isset($_POST['Manage_orders']) ? 'yes' : 'no';
    $Stock_Management = isset($_POST['Stock_Management']) ? 'yes' : 'no';
    $Manage_expence = isset($_POST['Manage_expence']) ? 'yes' : 'no';
    $Facebook_ads = isset($_POST['Facebook_ads']) ? 'yes' : 'no';
    $Monthly_reports = isset($_POST['Monthly_reports']) ? 'yes' : 'no';
    $Resellers = isset($_POST['Resellers']) ? 'yes' : 'no';

    $stmt = $pdo->prepare("UPDATE new_admins SET 
        dashboard = :dashboard,
        Manage_products = :Manage_products,
        Manage_orders = :Manage_orders,
        Stock_Management = :Stock_Management,
        Manage_expence = :Manage_expence,
        Facebook_ads = :Facebook_ads,
        Monthly_reports = :Monthly_reports,
        Resellers = :Resellers
        WHERE id = :id");
    $stmt->execute([
        ':dashboard' => $dashboard,
        ':Manage_products' => $Manage_products,
        ':Manage_orders' => $Manage_orders,
        ':Stock_Management' => $Stock_Management,
        ':Manage_expence' => $Manage_expence,
        ':Facebook_ads' => $Facebook_ads,
        ':Monthly_reports' => $Monthly_reports,
        ':Resellers' => $Resellers,
        ':id' => $id
    ]);
}

// Fetch admins
$stmt = $pdo->query("SELECT * FROM new_admins ORDER BY id DESC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage Admins</title>
</head>

<body>
    <h2>All Admins</h2>
    <table border="1" cellpadding="8">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Address</th>
            <th>Phone Number</th>
            <th>Dashboard</th>
            <th>Manage Products</th>
            <th>Manage Orders</th>
            <th>Stock Management</th>
            <th>Manage Expence</th>
            <th>Facebook Ads</th>
            <th>Monthly Reports</th>
            <th>Resellers</th>
            <th>Update</th>
        </tr>
        <?php foreach ($admins as $admin): ?>
            <tr>
                <form method="post">
                    <td><?php echo htmlspecialchars($admin['id']); ?></td>
                    <td><?php echo htmlspecialchars($admin['name']); ?></td>
                    <td><?php echo htmlspecialchars($admin['address']); ?></td>
                    <td><?php echo htmlspecialchars($admin['phone_number']); ?></td>
                    <td><input type="checkbox" name="dashboard" <?php if ($admin['dashboard'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Manage_products" <?php if ($admin['Manage_products'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Manage_orders" <?php if ($admin['Manage_orders'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Stock_Management" <?php if ($admin['Stock_Management'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Manage_expence" <?php if ($admin['Manage_expence'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Facebook_ads" <?php if ($admin['Facebook_ads'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Monthly_reports" <?php if ($admin['Monthly_reports'] === 'yes') echo 'checked'; ?>></td>
                    <td><input type="checkbox" name="Resellers" <?php if ($admin['Resellers'] === 'yes') echo 'checked'; ?>></td>
                    <td>
                        <input type="hidden" name="update_id" value="<?php echo $admin['id']; ?>">
                        <button type="submit">Update</button>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>
    <br>
    <a href="add_admin.php">Add Admin</a>
</body>

</html>