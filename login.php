<?php
require_once 'config/database.php';
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    
    if (empty($identifier) || empty($password)) {
        $error = "Please enter both email/phone and password";
    } else {
        $conn = connectDB();
        
        try {
            // First check if this is an admin account
            $admin_check = mysqli_prepare($conn, "
                SELECT 1 FROM users u 
                JOIN admin_users au ON u.id = au.user_id
                WHERE u.email = ? OR u.phone = ?
            ");
            mysqli_stmt_bind_param($admin_check, "ss", $identifier, $identifier);
            mysqli_stmt_execute($admin_check);
            
            if (mysqli_stmt_fetch($admin_check)) {
                // This is an admin account - redirect to admin login
                header("Location: admin/login.php");
                exit();
            }
            
            // Regular user login check
            $stmt = mysqli_prepare($conn, "
                SELECT * FROM users 
                WHERE (email = ? OR phone = ?)
                AND id NOT IN (SELECT user_id FROM admin_users)
            ");
            mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['is_verified'] = $user['is_verified'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid credentials";
            }
            
        } catch (Exception $e) {
            $error = "Error during login: " . $e->getMessage();
        }
        
        closeDB($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Login</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="identifier" class="form-label">Email address/Phone number</label>
                                <input type="text" class="form-control" id="identifier" name="identifier" required>
                                <div class="invalid-feedback">Please enter a valid email address or phone number</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback">Please enter your password</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>

                            <div class="text-center mt-3">
                                <p>Don't have an account? <a href="register.php">Register here</a></p>
                                <p><a href="forgot-password.php">Forgot Password?</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html>
