<?php
require_once 'config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user and profile information
$stmt = mysqli_prepare($conn, "
    SELECT 
        u.*,
        p.*,
        CASE 
            WHEN p.profile_pic IS NOT NULL 
            AND p.first_name IS NOT NULL 
            AND p.last_name IS NOT NULL 
            AND p.dob IS NOT NULL 
            AND p.gender IS NOT NULL 
            AND p.marital_status IS NOT NULL 
            AND p.religion IS NOT NULL 
            AND p.occupation IS NOT NULL 
            AND p.education IS NOT NULL 
            AND p.city IS NOT NULL 
            AND p.state IS NOT NULL 
            THEN TRUE 
            ELSE FALSE 
        END as profile_complete
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Set default values if not set
$user['verification_status'] = $user['verification_status'] ?? 'pending';
$user['email_verified'] = $user['email_verified'] ?? 0;
$user['phone_verified'] = $user['phone_verified'] ?? 0;
$user['profile_complete'] = $user['profile_complete'] ?? 0;
$user['first_name'] = $user['first_name'] ?? '';
$user['email'] = $user['email'] ?? '';
$user['phone'] = $user['phone'] ?? '';
$user['verification_notes'] = $user['verification_notes'] ?? '';
$user['phone_verification_code'] = $user['phone_verification_code'] ?? null;
$user['phone_code_expiry'] = $user['phone_code_expiry'] ?? null;

// Handle email verification request
if (isset($_POST['verify_email'])) {
    try {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = mysqli_prepare($conn, "
            UPDATE users 
            SET email_verification_token = ?,
                email_verification_expiry = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ssi", $token, $expiry, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Prepare email content
            $verification_link = "http://{$_SERVER['HTTP_HOST']}/matrimony/verify_email.php?token=" . $token;
            $to = $user['email'];
            $subject = "Verify Your Email - Indian Matrimony";
            $message = "Dear {$user['first_name']},\n\n";
            $message .= "Thank you for registering with Indian Matrimony. Please click the following link to verify your email address:\n\n";
            $message .= $verification_link . "\n\n";
            $message .= "This link will expire in 24 hours.\n\n";
            $message .= "If you did not request this verification, please ignore this email.\n\n";
            $message .= "Best regards,\nIndian Matrimony Team";
            
            // Email headers
            $headers = array(
                'From' => 'Indian Matrimony <noreply@indianmatrimony.com>',
                'Reply-To' => 'noreply@indianmatrimony.com',
                'X-Mailer' => 'PHP/' . phpversion(),
                'Content-Type' => 'text/plain; charset=UTF-8'
            );
            
            if (mail($to, $subject, $message, $headers)) {
                $success = "Verification email sent to {$user['email']}. Please check your inbox and spam folder.";
            } else {
                throw new Exception("Failed to send verification email. Please check your email configuration.");
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Reset the token if email fails
        $stmt = mysqli_prepare($conn, "
            UPDATE users 
            SET email_verification_token = NULL,
                email_verification_expiry = NULL
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
    }
}

// Handle phone verification request
if (isset($_POST['verify_phone'])) {
    try {
        $code = sprintf("%06d", mt_rand(100000, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmt = mysqli_prepare($conn, "
            UPDATE users 
            SET phone_verification_code = ?,
                phone_code_expiry = ?,
                phone_verification_attempts = 0
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ssi", $code, $expiry, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // In a real application, send this via SMS gateway
            $success = "Verification code sent to your phone: " . $code;
            // Refresh user data
            $user['phone_verification_code'] = $code;
            $user['phone_code_expiry'] = $expiry;
        } else {
            throw new Exception("Failed to generate verification code.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle phone code verification
if (isset($_POST['verify_phone_code'])) {
    try {
        $submitted_code = $_POST['phone_code'];
        
        // Check if code exists and is not expired
        if (empty($user['phone_verification_code'])) {
            throw new Exception("No verification code found. Please request a new one.");
        }
        
        if (strtotime($user['phone_code_expiry']) < time()) {
            throw new Exception("Verification code has expired. Please request a new one.");
        }
        
        // Verify the code
        if ($user['phone_verification_code'] === $submitted_code) {
            $stmt = mysqli_prepare($conn, "
                UPDATE users 
                SET phone_verified = TRUE,
                    phone_verification_code = NULL,
                    phone_code_expiry = NULL,
                    phone_verification_attempts = 0
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Phone number verified successfully!";
                $user['phone_verified'] = true;
                $user['phone_verification_code'] = null;
            } else {
                throw new Exception("Failed to update verification status.");
            }
        } else {
            // Increment attempts
            $stmt = mysqli_prepare($conn, "
                UPDATE users 
                SET phone_verification_attempts = phone_verification_attempts + 1
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            
            throw new Exception("Invalid verification code. Please try again.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Update verification status based on completion
if ($user['profile_complete'] && $user['email_verified'] && $user['phone_verified'] && 
    $user['verification_status'] === 'pending') {
    
    try {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // First check if a verification request already exists
        $check_stmt = mysqli_prepare($conn, "
            SELECT id FROM verification_requests 
            WHERE user_id = ? AND status = 'pending'
        ");
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            // Update user status
            $update_stmt = mysqli_prepare($conn, "
                UPDATE users 
                SET verification_status = 'in_progress'
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update user verification status");
            }
            
            // Create verification request
            $request_stmt = mysqli_prepare($conn, "
                INSERT INTO verification_requests (user_id, status, request_date)
                VALUES (?, 'pending', NOW())
            ");
            mysqli_stmt_bind_param($request_stmt, "i", $user_id);
            
            if (!mysqli_stmt_execute($request_stmt)) {
                throw new Exception("Failed to create verification request");
            }
            
            mysqli_commit($conn);
            $user['verification_status'] = 'in_progress';
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

closeDB($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Verification - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Profile Verification</h4>
                        
                        <!-- Verification Status -->
                        <div class="alert <?php echo ($user['verification_status'] === 'verified') ? 'alert-success' : 'alert-info'; ?>">
                            <h5 class="alert-heading">
                                <i class="bi bi-info-circle me-2"></i>
                                Verification Status: <?php echo ucfirst($user['verification_status']); ?>
                            </h5>
                            <?php if ($user['verification_status'] === 'pending'): ?>
                                <p class="mb-0">Complete all verification steps to get your profile verified.</p>
                            <?php elseif ($user['verification_status'] === 'in_progress'): ?>
                                <p class="mb-0">Your verification request is being reviewed by our team.</p>
                            <?php elseif ($user['verification_status'] === 'verified'): ?>
                                <p class="mb-0">Your profile is verified! You can now enjoy all features.</p>
                            <?php elseif ($user['verification_status'] === 'rejected'): ?>
                                <p class="mb-0">Your verification was rejected. Please check admin notes and try again.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Verification Steps -->
                        <div class="list-group mb-4">
                            <!-- Profile Completion -->
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Complete Profile Information</h6>
                                        <p class="mb-1 small text-muted">Fill all required fields and upload profile picture</p>
                                    </div>
                                    <?php if ($user['profile_complete']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Complete</span>
                                    <?php else: ?>
                                        <a href="edit-profile.php" class="btn btn-primary btn-sm">Complete Profile</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Email Verification -->
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Verify Email Address</h6>
                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                    <?php if ($user['email_verified']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Verified</span>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <button type="submit" name="verify_email" class="btn btn-primary btn-sm">
                                                Send Verification Email
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Phone Verification -->
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Verify Phone Number</h6>
                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($user['phone']); ?></p>
                                    </div>
                                    <?php if ($user['phone_verified']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Verified</span>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <?php if (isset($user['phone_verification_code'])): ?>
                                                <div class="input-group">
                                                    <input type="text" name="phone_code" class="form-control form-control-sm" 
                                                           placeholder="Enter OTP" required>
                                                    <button type="submit" name="verify_phone_code" class="btn btn-primary btn-sm">
                                                        Verify
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <button type="submit" name="verify_phone" class="btn btn-primary btn-sm">
                                                    Send OTP
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Verification Notes -->
                        <?php if ($user['verification_notes']): ?>
                            <div class="alert alert-warning">
                                <h6 class="alert-heading">Notes from Admin:</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($user['verification_notes'])); ?></p>
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
