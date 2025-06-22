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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .sidebar {
            min-width: 220px; background: #212529; color: #fff; min-height: 100vh;
        }
        .sidebar h2 { padding: 1.5rem 1rem 1rem 1rem; font-size: 1.5rem; border-bottom: 1px solid #343a40; }
        .sidebar nav a {
            display: block; color: #adb5bd; padding: 0.75rem 1rem; text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        .sidebar nav a.active, .sidebar nav a:hover { background: #343a40; color: #fff; }
        .main-content { padding: 2.5rem 2rem; flex: 1; }
        .card {
            border-radius: 1.25rem; box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            margin-bottom: 2rem; background: #fff; border: none;
            max-width: 500px; margin-left: auto; margin-right: auto;
        }
        .form-label { font-weight: 600; color: #495057; }
        .form-control { border-radius: 0.5rem; border: 1px solid #e9ecef; background: #f8fafc; }
        .btn-primary {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            border: none; border-radius: 0.5rem; width: 100%; font-size: 1.1rem; padding: 0.75rem;
        }
        .btn-primary:hover { background: linear-gradient(90deg, #0056b3 0%, #007bff 100%); }
        .alert { margin-bottom: 1rem; }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; }
            .main-content { padding: 1rem; }
            .card { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <div class="card p-4">
                <h2 class="mb-4 text-center"><i class="fas fa-user-plus"></i> Add Admin</h2>
                <?php if ($message): ?>
                    <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form action="" method="post">
                    <div class="mb-3">
                        <label class="form-label">Admin Name</label>
                        <input type="text" name="admin_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Admin</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="manage_admins.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-users-cog"></i> Manage Admin</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>