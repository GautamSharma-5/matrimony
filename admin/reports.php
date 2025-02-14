<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$error = '';
$success = '';

// Get various statistics
try {
    // Total Users
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM users WHERE id NOT IN (SELECT user_id FROM admin_users)");
    mysqli_stmt_execute($stmt);
    $total_users = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    // Users by Gender
    $stmt = mysqli_prepare($conn, "
        SELECT p.gender, COUNT(*) as count 
        FROM profiles p 
        JOIN users u ON p.user_id = u.id 
        WHERE u.id NOT IN (SELECT user_id FROM admin_users)
        GROUP BY p.gender
    ");
    mysqli_stmt_execute($stmt);
    $gender_stats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Users by Religion
    $stmt = mysqli_prepare($conn, "
        SELECT p.religion, COUNT(*) as count 
        FROM profiles p 
        JOIN users u ON p.user_id = u.id 
        WHERE u.id NOT IN (SELECT user_id FROM admin_users)
        GROUP BY p.religion
    ");
    mysqli_stmt_execute($stmt);
    $religion_stats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Premium Users
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as premium_count 
        FROM users 
        WHERE is_premium = 1 
        AND id NOT IN (SELECT user_id FROM admin_users)
    ");
    mysqli_stmt_execute($stmt);
    $premium_users = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['premium_count'];

    // Users by Age Group
    $stmt = mysqli_prepare($conn, "
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) < 25 THEN '18-24'
                WHEN TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) < 30 THEN '25-29'
                WHEN TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) < 35 THEN '30-34'
                WHEN TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) < 40 THEN '35-39'
                ELSE '40+'
            END as age_group,
            COUNT(*) as count
        FROM profiles p
        JOIN users u ON p.user_id = u.id 
        WHERE u.id NOT IN (SELECT user_id FROM admin_users)
        GROUP BY age_group
        ORDER BY age_group
    ");
    mysqli_stmt_execute($stmt);
    $age_stats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // New Users This Month
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as new_users 
        FROM users 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        AND id NOT IN (SELECT user_id FROM admin_users)
    ");
    mysqli_stmt_execute($stmt);
    $new_users = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['new_users'];

} catch (Exception $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'includes/sidebar.php'; ?>
            </div>

            <div class="col-md-9">
                <h2 class="mb-4">
                    <i class="bi bi-graph-up me-2"></i>
                    Statistics & Reports
                </h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h2><?php echo number_format($total_users); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Premium Users</h5>
                                <h2><?php echo number_format($premium_users); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">New This Month</h5>
                                <h2><?php echo number_format($new_users); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <!-- Gender Distribution -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Gender Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="genderChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Age Distribution -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Age Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="ageChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Religion Distribution -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Religion Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="religionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare data for charts
        const genderData = <?php echo json_encode($gender_stats); ?>;
        const ageData = <?php echo json_encode($age_stats); ?>;
        const religionData = <?php echo json_encode($religion_stats); ?>;

        // Gender Chart
        new Chart(document.getElementById('genderChart'), {
            type: 'pie',
            data: {
                labels: genderData.map(item => item.gender),
                datasets: [{
                    data: genderData.map(item => item.count),
                    backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56']
                }]
            }
        });

        // Age Chart
        new Chart(document.getElementById('ageChart'), {
            type: 'bar',
            data: {
                labels: ageData.map(item => item.age_group),
                datasets: [{
                    label: 'Users by Age Group',
                    data: ageData.map(item => item.count),
                    backgroundColor: '#36A2EB'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Religion Chart
        new Chart(document.getElementById('religionChart'), {
            type: 'bar',
            data: {
                labels: religionData.map(item => item.religion),
                datasets: [{
                    label: 'Users by Religion',
                    data: religionData.map(item => item.count),
                    backgroundColor: '#FFCE56'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
