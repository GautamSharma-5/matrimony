<?php
require_once 'config/database.php';

$error = '';
$success = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $conn = connectDB();
    
    // Verify token and update email verification status
    $stmt = mysqli_prepare($conn, "
        UPDATE users 
        SET email_verified = TRUE,
            email_verification_token = NULL 
        WHERE email_verification_token = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $token);
    
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        $success = "Your email has been verified successfully! You can now close this window.";
    } else {
        $error = "Invalid or expired verification token.";
    }
    
    closeDB($conn);
} else {
    $error = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <h5 class="alert-heading">Verification Failed</h5>
                        <p class="mb-0"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h5 class="alert-heading">Email Verified!</h5>
                        <p class="mb-0"><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
