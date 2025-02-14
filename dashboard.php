<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;
$profile = null;
$profile_views = [];
$recent_visitors = [];
$matches = [];

try {
    $conn = connectDB();
    
    // Get user's profile
    $stmt = mysqli_prepare($conn, "
        SELECT p.*, u.email, u.phone
        FROM profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    
    // If profile doesn't exist, redirect to create profile
    if (!$profile) {
        header("Location: profile-create.php");
        exit();
    }
    
    // Get profile views count and recent visitors
    $stmt = mysqli_prepare($conn, "
        SELECT v.*, p.first_name, p.last_name, p.profile_pic
        FROM profile_views v
        JOIN profiles p ON v.viewer_id = p.user_id
        WHERE v.profile_id = ?
        ORDER BY v.view_date DESC
        LIMIT 5
    ");
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $profile['id']);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_visitors[] = $row;
    }
    
    // Get total profile views count
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) as view_count
        FROM profile_views
        WHERE profile_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $profile['id']);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $views_data = mysqli_fetch_assoc($result);
    $view_count = $views_data ? $views_data['view_count'] : 0;
    
    // Get potential matches
    $stmt = mysqli_prepare($conn, "
        SELECT p.*, u.email
        FROM profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id != ?
        AND p.gender != ?
        LIMIT 10
    ");
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "is", $user_id, $profile['gender']);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $matches[] = $row;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
} finally {
    if (isset($conn)) {
        closeDB($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Summary -->
            <div class="col-md-4">
                <div class="card shadow mb-4">
                    <div class="card-body text-center">
                        <?php 
                        $profile_name = htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <?php if ($profile['profile_pic']): ?>
                            <img src="<?php echo htmlspecialchars($profile['profile_pic'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" 
                                 alt="<?php echo $profile_name; ?>">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 150px; height: 150px;">
                                <span style="font-size: 4rem;"><?php echo strtoupper(substr($profile['first_name'], 0, 1)); ?></span>
                            </div>
                        <?php endif; ?>
                        <h4><?php echo $profile_name; ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($profile['occupation'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="edit-profile.php" class="btn btn-primary">Edit Profile</a>
                    </div>
                </div>

                <!-- Profile Stats -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Profile Stats</h5>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Profile Views</span>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($view_count, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Recent Visitors</span>
                            <span class="badge bg-primary"><?php echo htmlspecialchars(count($recent_visitors), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Visitors -->
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Recent Visitors</h5>
                        <?php if (empty($recent_visitors)): ?>
                            <p class="text-muted">No recent visitors</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_visitors as $visitor): ?>
                                    <div class="list-group-item d-flex align-items-center">
                                        <?php if ($visitor['profile_pic']): ?>
                                            <img src="<?php echo htmlspecialchars($visitor['profile_pic'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                 alt="Visitor Picture" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 50px; height: 50px;">
                                                <span style="font-size: 2rem;"><?php echo strtoupper(substr($visitor['first_name'], 0, 1)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                            <small class="text-muted">
                                                Visited <?php echo date('M j, Y g:i A', strtotime($visitor['view_date'])); ?>
                                            </small>
                                        </div>
                                        <a href="view-profile.php?id=<?php echo $visitor['viewer_id']; ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Potential Matches -->
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title">Potential Matches</h5>
                        <?php if (empty($matches)): ?>
                            <p class="text-muted">No matches found</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($matches as $match): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <?php if ($match['profile_pic']): ?>
                                                        <img src="<?php echo htmlspecialchars($match['profile_pic'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                             alt="Match Picture" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" 
                                                             style="width: 60px; height: 60px;">
                                                            <span style="font-size: 2rem;"><?php echo strtoupper(substr($match['first_name'], 0, 1)); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($match['first_name'] . ' ' . $match['last_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($match['occupation'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <a href="view-profile.php?id=<?php echo $match['user_id']; ?>" class="btn btn-sm btn-primary">View Profile</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
