<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage-users.php");
    exit();
}

$conn = connectDB();
$user_id = mysqli_real_escape_string($conn, $_GET['id']);
$error = '';
$success = '';
$verification = null;

try {
    // Get user details with profile information
    $stmt = mysqli_prepare($conn, "
        SELECT 
            u.*,
            p.*,
            p.created_at as profile_created_at,
            p.updated_at as profile_updated_at,
            u.created_at as user_created_at,
            u.updated_at as user_updated_at,
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as age,
            p.profile_pic as profile_picture
        FROM users u
        LEFT JOIN profiles p ON u.id = p.user_id
        WHERE u.id = ?
        AND u.id NOT IN (SELECT user_id FROM admin_users)
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        // Get verification request status if any
        $stmt = mysqli_prepare($conn, "
            SELECT status, request_date as created_at, reviewed_at as updated_at 
            FROM verification_requests 
            WHERE user_id = ? 
            ORDER BY request_date DESC 
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $verification = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    } else {
        header("Location: manage-users.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching user details: " . $e->getMessage();
}

// Initialize default values for nullable fields
$user['first_name'] = $user['first_name'] ?? '';
$user['last_name'] = $user['last_name'] ?? '';
$user['email'] = $user['email'] ?? '';
$user['phone'] = $user['phone'] ?? '';
$user['gender'] = $user['gender'] ?? '';
$user['religion'] = $user['religion'] ?? '';
$user['caste'] = $user['caste'] ?? '';
$user['marital_status'] = $user['marital_status'] ?? '';
$user['education'] = $user['education'] ?? '';
$user['occupation'] = $user['occupation'] ?? '';
$user['income'] = $user['income'] ?? '';
$user['city'] = $user['city'] ?? '';
$user['state'] = $user['state'] ?? '';
$user['country'] = $user['country'] ?? 'India';
$user['profile_picture'] = $user['profile_pic'] ?? '';
$user['is_premium'] = $user['is_premium'] ?? 0;
$user['is_verified'] = $user['is_verified'] ?? 0;
$user['show_profile'] = $user['show_profile'] ?? 1;
$user['user_created_at'] = $user['user_created_at'] ?? date('Y-m-d H:i:s');
$user['user_updated_at'] = $user['user_updated_at'] ?? date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .profile-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-person-badge me-2"></i>
                        User Profile
                    </h2>
                    <a href="manage-users.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>
                        Back to Users
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '../uploads/profile_pics/default-profile.jpg'; ?>" 
                                 alt="Profile Picture" class="profile-image mb-3">
                        </div>
                        <div class="col-md-6">
                            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <p class="text-muted mb-2">
                                <i class="bi bi-envelope me-2"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="bi bi-phone me-2"></i>
                                <?php echo htmlspecialchars($user['phone']); ?>
                            </p>
                            <div class="mt-3">
                                <?php if ($user['is_premium']): ?>
                                    <span class="badge bg-success status-badge me-2">Premium</span>
                                <?php endif; ?>
                                <?php if ($user['is_verified']): ?>
                                    <span class="badge bg-primary status-badge me-2">Verified</span>
                                <?php endif; ?>
                                <?php if (!$user['show_profile']): ?>
                                    <span class="badge bg-warning status-badge me-2">Hidden Profile</span>
                                <?php endif; ?>
                                <?php if ($verification): ?>
                                    <span class="badge bg-<?php echo $verification['status'] === 'pending' ? 'warning' : ($verification['status'] === 'approved' ? 'success' : 'danger'); ?> status-badge">
                                        Verification: <?php echo ucfirst($verification['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group">
                                <a href="edit-user.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil me-2"></i>
                                    Edit User
                                </a>
                                <?php if (!$user['is_verified']): ?>
                                    <a href="verify-users.php?action=verify&id=<?php echo $user_id; ?>" class="btn btn-success">
                                        <i class="bi bi-check-circle me-2"></i>
                                        Verify User
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Details -->
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">Age</th>
                                        <td><?php echo $user['age'] ?? 'Not specified'; ?> years</td>
                                    </tr>
                                    <tr>
                                        <th>Gender</th>
                                        <td><?php echo htmlspecialchars($user['gender']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Religion</th>
                                        <td><?php echo htmlspecialchars($user['religion']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Caste</th>
                                        <td><?php echo htmlspecialchars($user['caste']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Marital Status</th>
                                        <td><?php echo htmlspecialchars($user['marital_status']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Professional Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">Education</th>
                                        <td><?php echo htmlspecialchars($user['education']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Occupation</th>
                                        <td><?php echo htmlspecialchars($user['occupation']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Income</th>
                                        <td><?php echo htmlspecialchars($user['income']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Location Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">City</th>
                                        <td><?php echo htmlspecialchars($user['city']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>State</th>
                                        <td><?php echo htmlspecialchars($user['state']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Country</th>
                                        <td><?php echo htmlspecialchars($user['country']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Account Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">Joined</th>
                                        <td><?php echo date('F j, Y', strtotime($user['user_created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Updated</th>
                                        <td><?php echo date('F j, Y', strtotime($user['user_updated_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Account Status</th>
                                        <td>
                                            <?php if ($user['show_profile']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
