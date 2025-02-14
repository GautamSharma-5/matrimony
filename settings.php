<?php
require_once 'config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    $error_message = "Current password is incorrect";
                } elseif ($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match";
                } elseif (strlen($new_password) < 8) {
                    $error_message = "New password must be at least 8 characters long";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Password updated successfully";
                    } else {
                        $error_message = "Failed to update password";
                    }
                }
                break;

            case 'update_privacy':
                $show_profile = isset($_POST['show_profile']) ? 1 : 0;
                $show_contact = isset($_POST['show_contact']) ? 1 : 0;
                $show_photos = isset($_POST['show_photos']) ? 1 : 0;

                $stmt = mysqli_prepare($conn, "UPDATE users SET 
                    show_profile = ?, 
                    show_contact = ?, 
                    show_photos = ? 
                    WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "iiii", $show_profile, $show_contact, $show_photos, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Privacy settings updated successfully";
                } else {
                    $error_message = "Failed to update privacy settings";
                }
                break;

            case 'update_notifications':
                $email_matches = isset($_POST['email_matches']) ? 1 : 0;
                $email_messages = isset($_POST['email_messages']) ? 1 : 0;
                $email_interests = isset($_POST['email_interests']) ? 1 : 0;

                $stmt = mysqli_prepare($conn, "UPDATE users SET 
                    email_matches = ?, 
                    email_messages = ?, 
                    email_interests = ? 
                    WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "iiii", $email_matches, $email_messages, $email_interests, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Notification settings updated successfully";
                } else {
                    $error_message = "Failed to update notification settings";
                }
                break;

            case 'delete_account':
                $password = $_POST['confirm_delete_password'];

                // Verify password before deletion
                if (!password_verify($password, $user['password'])) {
                    $error_message = "Incorrect password";
                } else {
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    try {
                        // Delete user's profile
                        $stmt = mysqli_prepare($conn, "DELETE FROM profiles WHERE user_id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);

                        // Delete user's connections
                        $stmt = mysqli_prepare($conn, "DELETE FROM connections WHERE sender_id = ? OR receiver_id = ?");
                        mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
                        mysqli_stmt_execute($stmt);

                        // Delete user account
                        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);

                        mysqli_commit($conn);
                        session_destroy();
                        header('Location: index.php?message=account_deleted');
                        exit();
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error_message = "Failed to delete account. Please try again.";
                    }
                }
                break;
        }
    }
}

// Get updated user data after changes
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

closeDB($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Settings</h5>
                        <div class="nav flex-column nav-pills" role="tablist">
                            <button class="nav-link active text-start" data-bs-toggle="pill" data-bs-target="#password" type="button">
                                <i class="bi bi-key me-2"></i>Change Password
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#privacy" type="button">
                                <i class="bi bi-shield-lock me-2"></i>Privacy Settings
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#notifications" type="button">
                                <i class="bi bi-bell me-2"></i>Notifications
                            </button>
                            <button class="nav-link text-start text-danger" data-bs-toggle="pill" data-bs-target="#delete-account" type="button">
                                <i class="bi bi-trash me-2"></i>Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="tab-content">
                    <!-- Change Password -->
                    <div class="tab-pane fade show active" id="password">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Change Password</h5>
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" 
                                               minlength="8" required>
                                        <div class="form-text">Password must be at least 8 characters long</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" 
                                               minlength="8" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Settings -->
                    <div class="tab-pane fade" id="privacy">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Privacy Settings</h5>
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="update_privacy">
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="show_profile" class="form-check-input" 
                                                   <?php echo $user['show_profile'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Show my profile in search results</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="show_contact" class="form-check-input"
                                                   <?php echo $user['show_contact'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Show my contact details to matched profiles</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="show_photos" class="form-check-input"
                                                   <?php echo $user['show_photos'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Show my photos to all members</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        Save Privacy Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="tab-pane fade" id="notifications">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Notification Settings</h5>
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="update_notifications">
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_matches" class="form-check-input"
                                                   <?php echo $user['email_matches'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Email me when I have new matches</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_messages" class="form-check-input"
                                                   <?php echo $user['email_messages'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Email me when I receive new messages</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_interests" class="form-check-input"
                                                   <?php echo $user['email_interests'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Email me when someone shows interest</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        Save Notification Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Account -->
                    <div class="tab-pane fade" id="delete-account">
                        <div class="card shadow-sm border-danger">
                            <div class="card-body">
                                <h5 class="card-title text-danger">Delete Account</h5>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Warning: This action cannot be undone. All your data will be permanently deleted.
                                </div>
                                
                                <form method="POST" class="mt-4" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_account">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Enter your password to confirm</label>
                                        <input type="password" name="confirm_delete_password" class="form-control" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash me-2"></i>Delete My Account
                                    </button>
                                </form>
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
