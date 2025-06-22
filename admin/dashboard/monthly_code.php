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
    <title>Monthly Code Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #f8fafc;
        }
        body, .d-flex {
            min-height: 100vh;
            height: 100%;
        }
        .d-flex {
            display: flex;
            min-height: 100vh;
            height: 100%;
        }
        .sidebar {
            min-width: 220px;
            max-width: 220px;
            background: #212529;
            color: #fff;
            min-height: 100vh;
            height: 100vh;
            position: relative;
            z-index: 2;
            box-shadow: 2px 0 8px rgba(0,0,0,0.04);
        }
        .sidebar h2 {
            padding: 1.5rem 1rem 1rem 1rem;
            font-size: 1.5rem;
            border-bottom: 1px solid #343a40;
            margin-bottom: 0;
        }
        .sidebar nav a {
            display: block;
            color: #adb5bd;
            padding: 0.75rem 1rem;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        .sidebar nav a.active, .sidebar nav a:hover {
            background: #343a40;
            color: #fff;
        }
        .main-content {
            flex: 1;
            padding: 2.5rem 2rem;
            min-height: 100vh;
            background: #f8fafc;
            margin-left: 0;
        }
        .card {
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            margin-bottom: 2rem;
            background: #fff;
            border: none;
        }
        .title {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
            margin-bottom: 2rem;
            font-weight: 700;
            color: #212529;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .filter-form label {
            font-weight: 600;
            color: #495057;
            margin-right: 0.5rem;
        }
        .filter-form select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #fff;
            padding: 0.4rem 1rem;
            font-size: 1rem;
        }
        .filter-form button {
            border-radius: 0.5rem;
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: #fff;
            border: none;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .filter-form button:hover {
            background: linear-gradient(90deg, #0056b3 0%, #007bff 100%);
        }
        .code-form-container {
            max-width: 400px;
            margin: 0 auto;
        }
        .code-form-card {
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            background: #fff;
            border: none;
            padding: 2rem;
        }
        .top-bar {
            font-size: 2rem;
            margin-bottom: 2rem;
            font-weight: 700;
            color: #212529;
            text-align: center;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            background: #f8fafc;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .btn-primary {
            width: 100%;
            font-size: 1.1rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #0056b3 0%, #007bff 100%);
        }
        @media (max-width: 991px) {
            .main-content { padding: 1rem; }
            .title { font-size: 1.2rem; }
            .filter-form { flex-direction: column; align-items: flex-start; gap: 0.7rem; }
        }
        @media (max-width: 768px) {
            .sidebar { min-width: 100px; max-width: 100px; }
            .main-content { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content d-flex align-items-center justify-content-center" style="min-height: 100vh;">
            <div class="code-form-container w-100">
                <div class="code-form-card">
                    <div class="top-bar mb-4">Enter Code</div>
                    <form method="POST">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="code" class="form-label"><i class="fas fa-key"></i> Access Code</label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="Enter Access Code" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>