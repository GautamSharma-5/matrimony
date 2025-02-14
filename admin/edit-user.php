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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic user information
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Profile information
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING);
        $religion = filter_input(INPUT_POST, 'religion', FILTER_SANITIZE_STRING);
        $caste = filter_input(INPUT_POST, 'caste', FILTER_SANITIZE_STRING);
        $marital_status = filter_input(INPUT_POST, 'marital_status', FILTER_SANITIZE_STRING);
        $education = filter_input(INPUT_POST, 'education', FILTER_SANITIZE_STRING);
        $occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING);
        $income = filter_input(INPUT_POST, 'income', FILTER_SANITIZE_STRING);
        $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
        $state = filter_input(INPUT_POST, 'state', FILTER_SANITIZE_STRING);
        $country = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);

        // Start transaction
        mysqli_begin_transaction($conn);

        // Update users table
        $stmt = mysqli_prepare($conn, "
            UPDATE users 
            SET email = ?, phone = ?, is_premium = ?, is_verified = ?, is_active = ?, updated_at = NOW()
            WHERE id = ? AND id NOT IN (SELECT user_id FROM admin_users)
        ");
        mysqli_stmt_bind_param($stmt, "ssiiis", $email, $phone, $is_premium, $is_verified, $is_active, $user_id);
        mysqli_stmt_execute($stmt);

        // Update profiles table
        $stmt = mysqli_prepare($conn, "
            UPDATE profiles 
            SET first_name = ?, last_name = ?, gender = ?, dob = ?, religion = ?, 
                caste = ?, marital_status = ?, education = ?, occupation = ?, 
                income = ?, city = ?, state = ?, country = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ssssssssssssss", 
            $first_name, $last_name, $gender, $dob, $religion, 
            $caste, $marital_status, $education, $occupation, 
            $income, $city, $state, $country, $user_id
        );
        mysqli_stmt_execute($stmt);

        // Commit transaction
        mysqli_commit($conn);
        $success = "User information updated successfully!";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Get user details
try {
    $stmt = mysqli_prepare($conn, "
        SELECT 
            u.*,
            p.*
        FROM users u
        LEFT JOIN profiles p ON u.id = p.user_id
        WHERE u.id = ?
        AND u.id NOT IN (SELECT user_id FROM admin_users)
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!($user = mysqli_fetch_assoc($result))) {
        header("Location: manage-users.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching user details: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Dashboard</title>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit User
                    </h2>
                    <div>
                        <a href="view-user.php?id=<?php echo $user_id; ?>" class="btn btn-outline-primary me-2">
                            <i class="bi bi-eye me-2"></i>
                            View Profile
                        </a>
                        <a href="manage-users.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>
                            Back to Users
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <!-- Account Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Account Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_premium" class="form-check-input" 
                                               <?php echo $user['is_premium'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Premium User</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_verified" class="form-check-input" 
                                               <?php echo $user['is_verified'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Verified User</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" class="form-check-input" 
                                               <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Active Account</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo $user['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $user['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['dob']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" class="form-select" required>
                                        <option value="">Select Status</option>
                                        <option value="Single" <?php echo $user['marital_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="Divorced" <?php echo $user['marital_status'] === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="Widowed" <?php echo $user['marital_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Religion</label>
                                    <input type="text" name="religion" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['religion']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Caste</label>
                                    <input type="text" name="caste" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['caste']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Professional Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Education</label>
                                    <input type="text" name="education" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['education']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Occupation</label>
                                    <input type="text" name="occupation" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['occupation']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Income</label>
                                    <input type="text" name="income" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['income']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Location Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['city']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="state" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['state']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['country']); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
