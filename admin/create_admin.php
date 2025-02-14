<?php
require_once '../config/database.php';
session_start();

// Only allow this script to run in development/setup
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('This script can only be run locally');
}

$conn = connectDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    
    if (empty($email) || empty($phone) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = mysqli_prepare($conn, "
                INSERT INTO users (email, phone, password, is_verified, email_verified, phone_verified, verification_status) 
                VALUES (?, ?, ?, 1, 1, 1, 'verified')
            ");
            mysqli_stmt_bind_param($stmt, "sss", $email, $phone, $hashed_password);
            mysqli_stmt_execute($stmt);
            
            $user_id = mysqli_insert_id($conn);
            
            // Create profile
            $stmt = mysqli_prepare($conn, "
                INSERT INTO profiles (user_id, first_name, last_name) 
                VALUES (?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $first_name, $last_name);
            mysqli_stmt_execute($stmt);
            
            // Create admin user
            $stmt = mysqli_prepare($conn, "
                INSERT INTO admin_users (user_id, role) 
                VALUES (?, 'super_admin')
            ");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($conn);
            
            // Set session variables and redirect to admin dashboard
            $_SESSION['user_id'] = $user_id;
            $_SESSION['admin_role'] = 'super_admin';
            $_SESSION['is_verified'] = true;
            
            header("Location: dashboard.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error creating admin account: " . $e->getMessage();
        }
    }
}

closeDB($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Create Admin Account</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Create Admin Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
