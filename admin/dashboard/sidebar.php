<?php

if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/config.php';

// Main admin: show all tabs
if (isset($_SESSION['admin_logged_in']) && !isset($_SESSION['is_other_admin'])) {
    $sidebarLinks = [
        ['href' => 'index.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
        ['href' => '../products/manage_products.php', 'icon' => 'fas fa-boxes', 'label' => 'Manage Products'],
        ['href' => '../orders/manage_orders.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'Manage Orders'],
        ['href' => '../stock/code.php', 'icon' => 'fas fa-cogs', 'label' => 'Stock Management'],
        ['href' => '../expenses/manage_expenses.php', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Manage Expenses'],
        ['href' => '../facebook/view_ads.php', 'icon' => 'fab fa-facebook', 'label' => 'Facebook Ads'],
        ['href' => 'monthly_code.php', 'icon' => 'fas fa-chart-line', 'label' => 'Monthly Report'],
        ['href' => 'resellers.php', 'icon' => 'fas fa-user', 'label' => 'Re Sellers'],
        ['href' => 'add_admin.php', 'icon' => 'fas fa-user', 'label' => 'Add Admin'],
        ['href' => '../auth/logout.php', 'icon' => 'fas fa-sign-out-alt', 'label' => 'Logout'],
    ];
} 
// Other admin: show only allowed tabs
elseif (isset($_SESSION['admin_logged_in']) && isset($_SESSION['is_other_admin']) && isset($_SESSION['admin_id'])) {
    $sidebarLinks = [
        ['href' => 'index.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
    ];
    $adminId = $_SESSION['admin_id'];
    $stmt = $pdo->prepare("SELECT * FROM new_admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if ($admin['Manage_products'] === 'yes') {
            $sidebarLinks[] = ['href' => '../products/manage_products.php', 'icon' => 'fas fa-boxes', 'label' => 'Manage Products'];
        }
        if ($admin['Manage_orders'] === 'yes') {
            $sidebarLinks[] = ['href' => '../orders/manage_orders.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'Manage Orders'];
        }
        if ($admin['Stock_Management'] === 'yes') {
            $sidebarLinks[] = ['href' => '../stock/code.php', 'icon' => 'fas fa-cogs', 'label' => 'Stock Management'];
        }
        if ($admin['Manage_expence'] === 'yes') {
            $sidebarLinks[] = ['href' => '../expenses/manage_expenses.php', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Manage Expenses'];
        }
        if ($admin['Facebook_ads'] === 'yes') {
            $sidebarLinks[] = ['href' => '../facebook/view_ads.php', 'icon' => 'fab fa-facebook', 'label' => 'Facebook Ads'];
        }
        if ($admin['Monthly_reports'] === 'yes') {
            $sidebarLinks[] = ['href' => 'monthly_code.php', 'icon' => 'fas fa-chart-line', 'label' => 'Monthly Report'];
        }
        if ($admin['Resellers'] === 'yes') {
            $sidebarLinks[] = ['href' => 'resellers.php', 'icon' => 'fas fa-user', 'label' => 'Re Sellers'];
        }
    }
    $sidebarLinks[] = ['href' => '../auth/logout.php', 'icon' => 'fas fa-sign-out-alt', 'label' => 'Logout'];
} else {
    // Not logged in, show nothing or redirect
    $sidebarLinks = [];
}
?>

<aside class="sidebar">
    <h2>Admin Panel</h2>
    <nav>
        <?php foreach ($sidebarLinks as $link): ?>
            <a href="<?= htmlspecialchars($link['href']) ?>"<?= (basename($_SERVER['PHP_SELF']) === basename($link['href'])) ? ' class="active"' : '' ?>>
                <i class="<?= htmlspecialchars($link['icon']) ?>"></i> <?= htmlspecialchars($link['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>