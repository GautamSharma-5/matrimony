<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
session_start();

// Debug session
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in - redirecting to login");
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
error_log("User ID from session: " . $user_id);

$error = null;
$success = null;

try {
    $conn = connectDB();
    
    // First verify that the user exists
    $check_user = mysqli_prepare($conn, "SELECT id, email FROM users WHERE id = ?");
    if (!$check_user) {
        throw new Exception("Failed to prepare user check query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($check_user, "i", $user_id);
    if (!mysqli_stmt_execute($check_user)) {
        throw new Exception("Failed to execute user check query: " . mysqli_stmt_error($check_user));
    }
    
    $user_result = mysqli_stmt_get_result($check_user);
    $user_data = mysqli_fetch_assoc($user_result);
    if (!$user_data) {
        error_log("User not found in database. User ID: " . $user_id);
        throw new Exception("User not found. Please log in again.");
    }
    error_log("Found user in database: " . print_r($user_data, true));
    mysqli_stmt_close($check_user);
    
    // Check if profile already exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM profiles WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_fetch_assoc($result)) {
        header("Location: edit-profile.php");
        exit();
    }
    
    mysqli_stmt_close($stmt);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize input
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $religion = trim($_POST['religion'] ?? '');
        $caste = trim($_POST['caste'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $income = trim($_POST['income'] ?? '');
        $education = trim($_POST['education'] ?? '');
        $marital_status = trim($_POST['marital_status'] ?? '');
        $height = isset($_POST['height']) ? floatval($_POST['height']) : null;
        $about_me = trim($_POST['about_me'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($religion)) {
            $error = "Please fill in all required fields";
        } else {
            // Calculate age
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $birthDate->diff($today)->y;
            
            if ($age < 18) {
                $error = "You must be at least 18 years old";
            } else {
                // Handle profile picture upload
                $profile_pic = null;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['profile_pic']['type'], $allowed_types)) {
                        $error = "Invalid file type. Only JPG, JPEG & PNG files are allowed";
                    } elseif ($_FILES['profile_pic']['size'] > $max_size) {
                        $error = "File size too large. Maximum size is 5MB";
                    } else {
                        // Create uploads directory if it doesn't exist
                        $upload_dir = 'uploads/profile_pics/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                        $filename = $user_id . '_' . time() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
                            $profile_pic = $target_path;
                        } else {
                            $error = "Failed to upload profile picture";
                        }
                    }
                }
                
                if (!$error) {
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Create profile with proper NULL handling
                        $stmt = mysqli_prepare($conn, "
                            INSERT INTO profiles (
                                user_id, first_name, last_name, gender, dob,
                                religion, caste, occupation, income,
                                education, marital_status, height,
                                about_me, city, state, country, profile_pic
                            ) VALUES (
                                ?, ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?,
                                ?, ?, ?, ?, ?
                            )
                        ");
                        
                        if (!$stmt) {
                            throw new Exception("Query preparation failed: " . mysqli_error($conn));
                        }
                        
                        // Set empty strings to NULL for optional fields
                        $caste = empty($caste) ? null : $caste;
                        $occupation = empty($occupation) ? null : $occupation;
                        $income = empty($income) ? null : $income;
                        $education = empty($education) ? null : $education;
                        $marital_status = empty($marital_status) ? null : $marital_status;
                        $height = empty($height) ? null : $height;
                        $about_me = empty($about_me) ? null : $about_me;
                        $city = empty($city) ? null : $city;
                        $state = empty($state) ? null : $state;
                        $country = empty($country) ? 'India' : $country;
                        
                        mysqli_stmt_bind_param($stmt, "issssssssssdsssss",
                            $user_id,
                            $first_name,
                            $last_name,
                            $gender,
                            $dob,
                            $religion,
                            $caste,
                            $occupation,
                            $income,
                            $education,
                            $marital_status,
                            $height,
                            $about_me,
                            $city,
                            $state,
                            $country,
                            $profile_pic
                        );
                        
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Profile creation failed: " . mysqli_stmt_error($stmt));
                        }
                        
                        mysqli_commit($conn);
                        $success = "Profile created successfully";
                        
                        // Redirect to dashboard after successful profile creation
                        header("Location: dashboard.php");
                        exit();
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = $e->getMessage();
                        
                        // Delete uploaded file if profile creation fails
                        if ($profile_pic && file_exists($profile_pic)) {
                            unlink($profile_pic);
                        }
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
} finally {
    if (isset($conn)) {
        closeDB($conn);
    }
}

// Helper function to safely display form values
function displayValue($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Profile - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Create Your Profile</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo displayValue($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo displayValue($success); ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row">
                                <!-- Profile Picture -->
                                <div class="col-12 mb-4">
                                    <label for="profile_pic" class="form-label">Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                                    <div class="form-text">Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG</div>
                                </div>

                                <!-- Personal Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    <div class="invalid-feedback">Please enter your first name</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    <div class="invalid-feedback">Please enter your last name</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Choose...</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your gender</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="dob" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="dob" name="dob" required>
                                    <div class="invalid-feedback">Please enter your date of birth</div>
                                </div>

                                <!-- Religious Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="religion" class="form-label">Religion *</label>
                                    <input type="text" class="form-control" id="religion" name="religion" required>
                                    <div class="invalid-feedback">Please enter your religion</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="caste" class="form-label">Caste</label>
                                    <input type="text" class="form-control" id="caste" name="caste">
                                </div>

                                <!-- Professional Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="occupation" class="form-label">Occupation *</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation" required>
                                    <div class="invalid-feedback">Please enter your occupation</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="income" class="form-label">Annual Income</label>
                                    <input type="text" class="form-control" id="income" name="income">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="education" class="form-label">Education</label>
                                    <input type="text" class="form-control" id="education" name="education">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="marital_status" class="form-label">Marital Status</label>
                                    <select class="form-select" id="marital_status" name="marital_status">
                                        <option value="">Choose...</option>
                                        <option value="Never Married">Never Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="height" class="form-label">Height (in cm)</label>
                                    <input type="number" step="0.01" class="form-control" id="height" name="height">
                                </div>

                                <!-- Location Information -->
                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country">
                                </div>

                                <!-- About Me -->
                                <div class="col-12 mb-3">
                                    <label for="about_me" class="form-label">About Me</label>
                                    <textarea class="form-control" id="about_me" name="about_me" rows="4"></textarea>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary">Create Profile</button>
                                <a href="dashboard.php" class="btn btn-secondary ms-2">Cancel</a>
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
