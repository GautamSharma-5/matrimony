<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$admin_id = $_SESSION['user_id'];

// Get total users count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM users WHERE id != ?");
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$total_users = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

// Get pending verification count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as pending FROM verification_requests WHERE status = 'pending'");
mysqli_stmt_execute($stmt);
$pending_verifications = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['pending'];

// Get today's registrations
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) as today 
    FROM users 
    WHERE DATE(created_at) = CURDATE() AND id != ?
");
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$today_registrations = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['today'];

// Get recent users
$stmt = mysqli_prepare($conn, "
    SELECT u.*, p.first_name, p.last_name, p.gender, p.city, p.state
    FROM users u
    JOIN profiles p ON u.id = p.user_id
    WHERE u.id != ?
    ORDER BY u.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$recent_users = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

closeDB($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <div class="col-md-9">
                <h2 class="mb-4">Admin Dashboard</h2>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h2 class="display-4"><?php echo $total_users; ?></h2>
                                <p class="card-text">Registered users</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Pending Verifications</h5>
                                <h2 class="display-4"><?php echo $pending_verifications; ?></h2>
                                <p class="card-text">Awaiting verification</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Today's Registrations</h5>
                                <h2 class="display-4"><?php echo $today_registrations; ?></h2>
                                <p class="card-text">New users today</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Users -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['city'] . ', ' . $user['state']); ?></td>
                                            <td>
                                                <?php if ($user['is_verified']): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="view-user.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
