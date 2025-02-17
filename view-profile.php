<?php
require_once 'config/database.php';
session_start();

$conn = connectDB();
$error_message = '';
$success_message = '';

// Check if profile ID is provided
if (!isset($_GET['id'])) {
    header('Location: search.php');
    exit();
}

$profile_id = intval($_GET['id']);

// Get profile data with user information
$stmt = mysqli_prepare($conn, "
    SELECT 
        p.*,
        u.email,
        u.phone,
        u.is_premium,
        u.show_contact,
        u.show_photos,
        u.is_verified,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as age
    FROM profiles p
    JOIN users u ON u.id = p.user_id
    WHERE u.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);

// If profile doesn't exist, redirect to search
if (!$profile) {
    header('Location: search.php?error=profile_not_found');
    exit();
}

// If profile is not verified, show message
if (!$profile['is_verified']) {
    $error_message = "This profile is currently under verification. Please check back later.";
}

// Track profile view if viewer is logged in and not viewing their own profile
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $profile_id) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO profile_views (profile_id, viewer_id) 
        VALUES ((SELECT id FROM profiles WHERE user_id = ?), ?)
    ");
    mysqli_stmt_bind_param($stmt, "ii", $profile_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
}

// Handle send interest action
if (isset($_POST['action']) && $_POST['action'] === 'send_interest') {
    if (!isset($_SESSION['user_id'])) {
        $error_message = "Please login to send interest";
    } else if ($_SESSION['user_id'] === $profile_id) {
        $error_message = "You cannot send interest to yourself";
    } else {
        // Check if interest already sent
        $stmt = mysqli_prepare($conn, "
            SELECT id FROM connections 
            WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
        ");
        mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $profile_id);
        mysqli_stmt_execute($stmt);
        $existing_interest = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($existing_interest) {
            $error_message = "You have already sent interest to this profile";
        } else {
            // Send interest
            $stmt = mysqli_prepare($conn, "
                INSERT INTO connections (sender_id, receiver_id, status) 
                VALUES (?, ?, 'pending')
            ");
            mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $profile_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Interest sent successfully";
            } else {
                $error_message = "Failed to send interest. Please try again.";
            }
        }
    }
}

// Get profile views count
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) as views 
    FROM profile_views 
    WHERE profile_id = (SELECT id FROM profiles WHERE user_id = ?)
");
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$views = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['views'];

// Get interests received count
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) as interests 
    FROM connections 
    WHERE receiver_id = ? AND status = 'pending'
");
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$interests = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['interests'];

closeDB($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?> - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Summary -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center">
                        <?php if ($profile['show_photos']): ?>
                            <?php if ($profile['profile_pic']): ?>
                                <img src="<?php echo htmlspecialchars($profile['profile_pic']); ?>" 
                                     class="rounded-circle mb-3" alt="Profile Picture"
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3"
                                     style="width: 150px; height: 150px;">
                                    <i class="bi bi-person text-secondary" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3"
                                 style="width: 150px; height: 150px;">
                                <i class="bi bi-lock text-secondary" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>

                        <h4 class="mb-0">
                            <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>
                            <?php if ($profile['is_premium']): ?>
                                <i class="bi bi-patch-check-fill text-primary" title="Premium Member"></i>
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted">
                            <?php echo $profile['age']; ?> years • 
                            <?php echo htmlspecialchars($profile['religion']); ?>
                            <?php if ($profile['caste']): ?>
                                • <?php echo htmlspecialchars($profile['caste']); ?>
                            <?php endif; ?>
                        </p>

                        <div class="d-flex justify-content-center gap-3 mb-3">
                            <div class="text-center">
                                <h5 class="mb-0"><?php echo $views; ?></h5>
                                <small class="text-muted">Profile Views</small>
                            </div>
                            <div class="text-center">
                                <h5 class="mb-0"><?php echo $interests; ?></h5>
                                <small class="text-muted">Interests</small>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $profile_id): ?>
                            <form method="POST" class="d-grid gap-2">
                                <input type="hidden" name="action" value="send_interest">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-heart me-2"></i>Send Interest
                                </button>
                                <?php if ($profile['show_contact']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-envelope me-2"></i>Send Email
                                    </a>
                                    <?php if ($profile['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($profile['phone']); ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-telephone me-2"></i>Call Now
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Basic Details -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Basic Details</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="bi bi-person me-2"></i>
                                <strong>Gender:</strong> <?php echo htmlspecialchars($profile['gender']); ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-calendar me-2"></i>
                                <strong>Age:</strong> <?php echo $profile['age']; ?> years
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-heart me-2"></i>
                                <strong>Marital Status:</strong> <?php echo htmlspecialchars($profile['marital_status'] ?? ''); ?>
                            </li>
                            <?php if ($profile['height']): ?>
                                <li class="mb-2">
                                    <i class="bi bi-rulers me-2"></i>
                                    <strong>Height:</strong> <?php echo number_format($profile['height'], 2); ?> cm
                                </li>
                            <?php endif; ?>
                            <li class="mb-2">
                                <i class="bi bi-geo-alt me-2"></i>
                                <strong>Location:</strong> 
                                <?php echo htmlspecialchars($profile['city'] . ', ' . $profile['state']); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="col-md-8">
                <!-- About Me -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">About Me</h5>
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($profile['about_me'] ?? 'No description provided.')); ?>
                        </p>
                    </div>
                </div>

                <!-- Career & Education -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Career & Education</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Career</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-briefcase me-2"></i>
                                        <strong>Occupation:</strong> <?php echo htmlspecialchars($profile['occupation']); ?>
                                    </li>
                                    <?php if ($profile['income']): ?>
                                        <li class="mb-2">
                                            <i class="bi bi-currency-dollar me-2"></i>
                                            <strong>Annual Income:</strong> <?php echo htmlspecialchars($profile['income']); ?>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">Education</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-book me-2"></i>
                                        <strong>Education:</strong> <?php echo htmlspecialchars($profile['education'] ?? 'Not specified'); ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Religious Background -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Religious Background</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-pray me-2"></i>
                                        <strong>Religion:</strong> <?php echo htmlspecialchars($profile['religion']); ?>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <?php if ($profile['caste']): ?>
                                        <li class="mb-2">
                                            <i class="bi bi-people me-2"></i>
                                            <strong>Caste:</strong> <?php echo htmlspecialchars($profile['caste']); ?>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $profile_id): ?>
                    <div class="text-end">
                        <a href="edit-profile.php" class="btn btn-primary">
                            <i class="bi bi-pencil me-2"></i>Edit Profile
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
