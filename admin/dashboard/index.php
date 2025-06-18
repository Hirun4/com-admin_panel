<?php
session_start();
include_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../auth/login.php");
    exit;
}

$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$formattedDate = date('F Y', strtotime($dateFilter));

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_products FROM products");
    $stmt->execute();
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS orders_today FROM orders WHERE DATE(created_at) = :dateFilter");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $ordersToday = $stmt->fetch(PDO::FETCH_ASSOC)['orders_today'];

    $stmt = $pdo->prepare("
        SELECT SUM(oi.final_price) AS total_revenue 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE MONTH(o.created_at) = MONTH(:dateFilter) AND YEAR(o.created_at) = YEAR(:dateFilter)
    ");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT 
            SUM(oi.final_price) AS total_income,
            SUM(oi.final_price - (SELECT pc.buying_price FROM product_codes pc WHERE pc.code = oi.buying_price_code)*oi.quantity) AS profit
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.delivery_method = 'Home' AND DATE(o.created_at) = :dateFilter
    ");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $homeStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT 
            SUM(oi.final_price) AS total_income,
            SUM(oi.final_price - (SELECT pc.buying_price FROM product_codes pc WHERE pc.code = oi.buying_price_code)*oi.quantity) AS profit
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.delivery_method = 'Courier' AND DATE(o.created_at) = :dateFilter
    ");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $courierStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT p.origin_country, COUNT(*) AS sales_count 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        GROUP BY p.origin_country
    ");
    $stmt->execute();
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $salesChart = [
        'Vietnam' => 0,
        'India' => 0,
        'China' => 0,
        'Local' => 0
    ];

    foreach ($salesData as $row) {
        $country = $row['origin_country'];
        $salesChart[$country] = (int)$row['sales_count'];
    }

    $stmt = $pdo->prepare("
        SELECT p.category, COUNT(*) AS category_count 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        GROUP BY p.category
    ");
    $stmt->execute();
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categoryChart = [
        'Ladies' => 0,
        'Gents' => 0,
        'Kids' => 0
    ];

    foreach ($categoryData as $row) {
        $category = $row['category'];
        $categoryChart[$category] = (int)$row['category_count'];
    }

    $stmt = $pdo->prepare("SELECT spender_name, description, amount, expense_date
                           FROM expenses
                           WHERE DAY(expense_date) BETWEEN 1 AND 15 AND MONTH(expense_date) = MONTH(:dateFilter) 
                           ORDER BY expense_date ASC");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $expensesFirstHalf = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT spender_name, description, amount, expense_date
                           FROM expenses
                           WHERE DAY(expense_date) BETWEEN 16 AND 31 AND MONTH(expense_date) = MONTH(:dateFilter) 
                           ORDER BY expense_date ASC");
    $stmt->bindParam(':dateFilter', $dateFilter);
    $stmt->execute();
    $expensesSecondHalf = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch total products grouped by country
    $stmt = $pdo->prepare("
        SELECT origin_country, SUM(stock_quantity) AS total_quantity
        FROM products
        GROUP BY origin_country
    ");
    $stmt->execute();
    $countryProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch buying price codes and sizes for each country
    $stmt = $pdo->prepare("
        SELECT p.origin_country, pc.code, ps.size, SUM(ps.quantity) AS total_quantity
        FROM products p
        JOIN product_codes pc ON p.buying_price_code = pc.code
        JOIN product_stock ps ON p.product_id = ps.product_id
        GROUP BY p.origin_country, pc.code, ps.size
        ORDER BY p.origin_country, pc.code, ps.size
    ");
    $stmt->execute();
    $countryDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countryData = [];
    foreach ($countryDetails as $detail) {
        $country = $detail['origin_country'];
        $code = $detail['code'];
        $size = $detail['size'];
        $quantity = $detail['total_quantity'];

        if (!isset($countryData[$country])) {
            $countryData[$country] = [];
        }
        if (!isset($countryData[$country][$code])) {
            $countryData[$country][$code] = [];
        }
        $countryData[$country][$code][$size] = $quantity;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$sizeData = [
    'Gents' => [],
    'Ladies' => [],
    'Kids' => []
];

try {
    $statement = $pdo->prepare("SELECT 
    p.category, 
    oi.size, 
    SUM(oi.quantity) AS total_quantity
FROM order_items oi
JOIN orders o ON oi.order_id = o.order_id
JOIN products p ON oi.product_id = p.product_id
GROUP BY p.category, oi.size
ORDER BY p.category, oi.size;
");
    $statement->execute();
    $results = $statement->fetchAll(PDO::FETCH_ASSOC);

    $sizeData = [];
    foreach ($results as $row) {
        $size = $row['size'];
        $category = $row['category'];
        $quantity = $row['total_quantity'];

        if (!isset($sizeData[$size])) {
            $sizeData[$size] = ['Gents' => 0, 'Ladies' => 0, 'Kids' => 0];
        }

        $sizeData[$size][$category] = $quantity;
    }
    // echo "<script>console.log('Size Data:', " . json_encode($sizeData) . ");</script>";

} catch (\Throwable $th) {
    // Handle error if needed
}

// Fetch count of customer order requests
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS request_count FROM customer_order_request");
    $stmt->execute();
    $orderRequestCount = $stmt->fetch(PDO::FETCH_ASSOC)['request_count'];
} catch (PDOException $e) {
    $orderRequestCount = 0;
}

// Fetch count of refund requests (only PENDING)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS refund_count FROM refund_requests WHERE status = 'PENDING'");
    $stmt->execute();
    $refundRequestCount = $stmt->fetch(PDO::FETCH_ASSOC)['refund_count'];
} catch (PDOException $e) {
    $refundRequestCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../products/manage_products.php"><i class="fas fa-boxes"></i> Manage Products</a>
                <a href="../orders/manage_orders.php"><i class="fas fa-clipboard-list"></i> Manage Orders</a>
                <a href="../stock/code.php"><i class="fas fa-cogs"></i> Stock Management</a>
                <a href="../expenses/manage_expenses.php"><i class="fas fa-money-bill-wave"></i> Manage Expenses</a>
                <a href="../facebook/view_ads.php"><i class="fab fa-facebook"></i> Facebook Ads</a>
                <a href="monthly_code.php"><i class="fas fa-chart-line"></i> Monthly Report</a>
                <a href="resellers.php"><i class="fas fa-user"></i> Re Sellers</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header>
                <h1>Dashboard</h1>
            </header>
            <div class="date-filter-container">
                <section class="date-filter">
                    <form method="GET" action="index.php">
                        <input type="date" id="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                        <button type="submit">Filter</button>
                    </form>
                </section>
                <p class="tip"><span>Tip:</span>Select a date and click "Filter" to view the relevant sales, expenses, and revenue data for that specific day.</p>
            </div>

            <section class="dashboard-stats">
                <div class="stat">
                    <h2>Total Products</h2>
                    <p><?= $totalProducts ?></p>
                </div>
                <div class="stat">
                    <h2>Orders on <?= htmlspecialchars($dateFilter) ?></h2>
                    <p><?= $ordersToday ?></p>
                </div>
                <div class="stat total-revenue">
                    <h2>Total Revenue for <?= $formattedDate ?></h2>
                    <p>Rs. <?= number_format($totalRevenue, 2) ?></p>
                </div>
                
                <div class="stat" id="orderRequestCard" style="cursor:pointer; background:#f1c40f;">
                    <h2>Order Requests</h2>
                    <p id="orderRequestCount"><?= $orderRequestCount ?></p>
                </div>

                <div class="stat" id="refundRequestCard" style="cursor:pointer; background:#e67e22;">
                    <h2>Refund Requests</h2>
                    <p id="refundRequestCount"><?= $refundRequestCount ?></p>
                </div>
            </section>

            <!-- Modal for order request details -->
            <div id="orderRequestModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; max-width:700px; width:90%; max-height:80vh; overflow:auto; position:relative;">
                    <button onclick="closeOrderRequestModal()" style="position:absolute; top:10px; right:10px;">&times;</button>
                    <h2>Order Requests Details</h2>
                    <div id="orderRequestDetails">Loading...</div>
                </div>
            </div>

            <!-- Modal for refund request details -->
            <div id="refundRequestModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; max-width:900px; width:95%; max-height:85vh; overflow:auto; position:relative;">
                    <button onclick="closeRefundRequestModal()" style="position:absolute; top:10px; right:10px;">&times;</button>
                    <h2>Refund Requests Details</h2>
                    <div id="refundRequestDetails">Loading...</div>
                </div>
            </div>

            <section style="display: flex; justify-content: space-between; margin-top: 30px;">
                <div style="flex: 1; margin-right: 20px;">
                    <h2>Sales by Origin Country</h2>
                    <canvas id="salesChart" style="width: 100%; height: 200px;"></canvas>
                </div>
                <div style="flex: 1;">
                    <h2>Orders by Category</h2>
                    <canvas id="categoryChart" style="width: 80%; max-width: 300px; height: 150px; margin: auto;"></canvas>
                </div>
            </section>
            <section style="display: flex; justify-content: space-between; margin-top: 30px;">
                <div style="flex: 1; margin-right: 20px; text-align: center;">
                    <h2>Shoe Sizes by Category</h2>
                    <canvas id="sizeCategoryChart" style="width: 70%; height: 300px;"></canvas>
                </div>

            </section>


            <section class="dashboard-revenue-profit" style="margin-top: 50px; display: flex; justify-content: space-between; flex-wrap: wrap;">
    <div class="revenue-column">
        <div class="revenue-item">
            <h2>Total Revenue (Home) on <?= htmlspecialchars($dateFilter) ?></h2>
            <p>Rs. <?= number_format($homeStats['total_income'], 2) ?></p>
        </div>
    </div>
    <div class="revenue-column">
        <div class="revenue-item">
            <h2>Total Revenue (Courier) on <?= htmlspecialchars($dateFilter) ?></h2>
            <p>Rs. <?= number_format($courierStats['total_income'], 2) ?></p>
        </div>
    </div>
</section>

            <section class="expenses-table" style="margin-top: 50px;">
                <h2>Expense Records</h2>

                <h3>Expenses (1-15)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Spender Name</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expensesFirstHalf)): ?>
                            <?php foreach ($expensesFirstHalf as $expense): ?>
                                <tr>
                                    <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                    <td><?= htmlspecialchars($expense['description']) ?></td>
                                    <td>Rs. <?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No expenses found for this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h3>Expenses (16-31)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Spender Name</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expensesSecondHalf)): ?>
                            <?php foreach ($expensesSecondHalf as $expense): ?>
                                <tr>
                                    <td><?= htmlspecialchars($expense['spender_name']) ?></td>
                                    <td><?= htmlspecialchars($expense['description']) ?></td>
                                    <td>Rs. <?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No expenses found for this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="country-products-table" style="margin-top: 50px;">
                <h2>Products by Country</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Total Quantity</th>
                            <th>Buying Price Codes</th>
                            <th>Sizes and Quantities</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($countryProducts as $countryProduct): ?>
                            <tr>
                                <td><?= htmlspecialchars($countryProduct['origin_country']) ?></td>
                                <td><?= htmlspecialchars($countryProduct['total_quantity']) ?></td>
                                <td>
                                    <button onclick="toggleCodes('<?= htmlspecialchars($countryProduct['origin_country']) ?>')">Show Codes</button>
                                    <div id="codes-<?= htmlspecialchars($countryProduct['origin_country']) ?>" style="display: none;">
                                        <?php if (isset($countryData[$countryProduct['origin_country']])): ?>
                                            <select onchange="showSizes(this, '<?= htmlspecialchars($countryProduct['origin_country']) ?>')">
                                                <option value="">Select Code</option>
                                                <?php foreach ($countryData[$countryProduct['origin_country']] as $code => $sizes): ?>
                                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            No codes available
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div id="sizes-<?= htmlspecialchars($countryProduct['origin_country']) ?>"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <script>
        const sizeData = <?= json_encode($sizeData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        // console.log("Size Data:", sizeData);
    </script>

    <script>
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: ['Vietnam', 'India', 'China', 'Local'],
                datasets: [{
                    label: 'Sales Count',
                    data: [<?= $salesChart['Vietnam'] ?>, <?= $salesChart['India'] ?>, <?= $salesChart['China'] ?>, <?= $salesChart['Local'] ?>],
                    backgroundColor: ['#3498db', '#e74c3c', '#2ecc71']
                }]
            }
        });

        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: ['Ladies', 'Gents', 'Kids'],
                datasets: [{
                    label: 'Category Distribution',
                    data: [<?= $categoryChart['Ladies'] ?>, <?= $categoryChart['Gents'] ?>, <?= $categoryChart['Kids'] ?>],
                    backgroundColor: ['#FF69B4', '#2980b9', '#27ae60']
                }]
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        console.log("Size Data:", sizeData);

        if (sizeData) {
            // Get unique sizes (keys of sizeData)
            const allSizes = Object.keys(sizeData).map(Number).sort((a, b) => a - b);

            console.log("All Sizes:", allSizes);

            // Prepare datasets for each category
            const categories = ['Gents', 'Ladies', 'Kids'];
            const datasets = categories.map(category => ({
                label: category,
                data: allSizes.map(size => Number(sizeData[size]?.[category] || 0)), // Convert to number
                backgroundColor: category === 'Gents' ? '#3498db' : category === 'Ladies' ? '#e74c3c' : '#2ecc71',
                borderColor: category === 'Gents' ? '#2980b9' : category === 'Ladies' ? '#c0392b' : '#27ae60',
                borderWidth: 1
            }));

            // Ensure canvas exists before rendering
            const canvas = document.getElementById('sizeCategoryChart');
            if (canvas) {
                const sizeCtx = canvas.getContext('2d');
                new Chart(sizeCtx, {
                    type: 'bar',
                    data: {
                        labels: allSizes, // X-axis: Sizes dynamically from the database
                        datasets: datasets // Each category (Gents, Ladies, Kids) has its own section
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            title: {
                                display: true,
                                text: 'Shoe Sizes by Category'
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Shoe Sizes'
                                },
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantity'
                                }
                            }
                        }
                    }
                });
            } else {
                console.error("Canvas element with ID 'sizeCategoryChart' not found!");
            }
        } else {
            console.error("sizeData is undefined or empty!");
        }
    </script>

    <script>
        function toggleCodes(country) {
            const codesDiv = document.getElementById(`codes-${country}`);
            if (codesDiv.style.display === 'none') {
                codesDiv.style.display = 'block';
            } else {
                codesDiv.style.display = 'none';
            }
        }

        function showSizes(select, country) {
            const code = select.value;
            const sizesDiv = document.getElementById(`sizes-${country}`);
            sizesDiv.innerHTML = '';

            if (code) {
                const sizes = <?= json_encode($countryData) ?>[country][code];
                const sizeSelect = document.createElement('select');
                sizeSelect.innerHTML = '<option value="">Select Size</option>';

                for (const size in sizes) {
                    const option = document.createElement('option');
                    option.value = size;
                    option.textContent = `Size ${size}: ${sizes[size]} units`;
                    sizeSelect.appendChild(option);
                }

                sizesDiv.appendChild(sizeSelect);
            }
        }
    </script>
    // Script to handle order request card click and modal display
    <script>
        document.getElementById('orderRequestCard').onclick = function() {
            document.getElementById('orderRequestModal').style.display = 'flex';
            fetch('order_request_details.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('orderRequestDetails').innerHTML = html;
                });
        };

        function closeOrderRequestModal() {
            document.getElementById('orderRequestModal').style.display = 'none';
        }
    </script>
    <script>
        document.getElementById('refundRequestCard').onclick = function() {
            document.getElementById('refundRequestModal').style.display = 'flex';
            fetch('refund_request_details.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('refundRequestDetails').innerHTML = html;
                });
        };

        function closeRefundRequestModal() {
            document.getElementById('refundRequestModal').style.display = 'none';
        }
    </script>
    <script>
        document.addEventListener('click', function(e) {
            // Accept
            if (e.target.classList.contains('accept-btn')) {
                const id = e.target.getAttribute('data-id');
                if (confirm('Are you sure you want to accept this order request?')) {
                    fetch('order_request_action.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=accept&id=' + encodeURIComponent(id)
                        })
                        .then(res => res.text())
                        .then(msg => {
                            alert(msg);
                            document.getElementById('orderRequestCard').click(); // Refresh modal
                        });
                }
            }
            // Reject
            if (e.target.classList.contains('reject-btn')) {
                const id = e.target.getAttribute('data-id');
                if (confirm('Are you sure you want to reject this order request?')) {
                    fetch('order_request_action.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=reject&id=' + encodeURIComponent(id)
                        })
                        .then(res => res.text())
                        .then(msg => {
                            alert(msg);
                            document.getElementById('orderRequestCard').click(); // Refresh modal
                        });
                }
            }
        });
    </script>
    <script>
        document.addEventListener('click', function(e) {
            // Accept refund
            if (e.target.classList.contains('accept-refund-btn')) {
                const id = e.target.getAttribute('data-id');
                const orderId = e.target.getAttribute('data-order-id');
                const refundAmount = e.target.getAttribute('data-amount');
                if (confirm('Are you sure you want to approve this refund request?')) {
                    fetch('refund_request_action.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=accept&id=' + encodeURIComponent(id) + '&order_id=' + encodeURIComponent(orderId) + '&refund_amount=' + encodeURIComponent(refundAmount)
                    })
                    .then(res => res.text())
                    .then(msg => {
                        alert(msg);
                        document.getElementById('refundRequestCard').click(); // Refresh modal
                        location.reload(); // Optionally refresh dashboard stats
                    });
                }
            }
            // Reject refund
            if (e.target.classList.contains('reject-refund-btn')) {
                const id = e.target.getAttribute('data-id');
                if (confirm('Are you sure you want to reject this refund request?')) {
                    fetch('refund_request_action.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=reject&id=' + encodeURIComponent(id)
                    })
                    .then(res => res.text())
                    .then(msg => {
                        alert(msg);
                        document.getElementById('refundRequestCard').click(); // Refresh modal
                        location.reload(); // Optionally refresh dashboard stats
                    });
                }
            }
        });
    </script>
</body>

</html>