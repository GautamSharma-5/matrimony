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

try {
    $conn = connectDB();
    
    // Get existing profile data
    $stmt = $conn->prepare("
        SELECT p.*, u.email, u.phone
        FROM profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: profile-create.php");
        exit();
    }
    
    $profile = $result->fetch_assoc();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize input
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $religion = isset($_POST['religion']) ? trim($_POST['religion']) : '';
        $caste = isset($_POST['caste']) ? trim($_POST['caste']) : '';
        $occupation = isset($_POST['occupation']) ? trim($_POST['occupation']) : '';
        $income = isset($_POST['income']) ? trim($_POST['income']) : '';
        $education = isset($_POST['education']) ? trim($_POST['education']) : '';
        $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
        $height = isset($_POST['height']) ? floatval($_POST['height']) : 0;
        $about_me = isset($_POST['about_me']) ? trim($_POST['about_me']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $state = isset($_POST['state']) ? trim($_POST['state']) : '';
        $country = isset($_POST['country']) ? trim($_POST['country']) : '';
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($religion) || empty($occupation)) {
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
                $profile_pic = $profile['profile_pic']; // Keep existing picture by default
                
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
                            // Delete old profile picture if it exists and is different
                            if ($profile['profile_pic'] && file_exists($profile['profile_pic']) && $profile['profile_pic'] !== $target_path) {
                                @unlink($profile['profile_pic']);
                            }
                            $profile_pic = $target_path;
                        } else {
                            $error = "Failed to upload profile picture. Error: " . error_get_last()['message'];
                        }
                    }
                }
                
                if (!$error) {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update profile
                        $stmt = $conn->prepare("
                            UPDATE profiles SET 
                            first_name = ?, last_name = ?, dob = ?, gender = ?,
                            religion = ?, caste = ?, occupation = ?, income = ?,
                            education = ?, marital_status = ?, height = ?,
                            about_me = ?, city = ?, state = ?, country = ?,
                            profile_pic = ?, updated_at = CURRENT_TIMESTAMP
                            WHERE user_id = ?
                        ");
                        
                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        $stmt->bind_param("ssssssssssdsssssi",
                            $first_name, $last_name, $dob, $gender,
                            $religion, $caste, $occupation, $income,
                            $education, $marital_status, $height,
                            $about_me, $city, $state, $country,
                            $profile_pic, $user_id
                        );
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Execute failed: " . $stmt->error);
                        }
                        
                        if ($stmt->affected_rows > 0 || $stmt->affected_rows === 0) {
                            $conn->commit();
                            $success = "Profile updated successfully";
                            // Refresh profile data
                            $profile['first_name'] = $first_name;
                            $profile['last_name'] = $last_name;
                            $profile['dob'] = $dob;
                            $profile['gender'] = $gender;
                            $profile['religion'] = $religion;
                            $profile['caste'] = $caste;
                            $profile['occupation'] = $occupation;
                            $profile['income'] = $income;
                            $profile['education'] = $education;
                            $profile['marital_status'] = $marital_status;
                            $profile['height'] = $height;
                            $profile['about_me'] = $about_me;
                            $profile['city'] = $city;
                            $profile['state'] = $state;
                            $profile['country'] = $country;
                            $profile['profile_pic'] = $profile_pic;
                        } else {
                            throw new Exception("No changes were made to the profile");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = $e->getMessage();
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
    <title>Edit Profile - Indian Matrimony</title>
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
                        <h3 class="card-title text-center mb-4">Edit Profile</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo displayValue($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo displayValue($success); ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Profile Picture -->
                            <div class="mb-4 text-center">
                                <?php if ($profile['profile_pic'] && file_exists($profile['profile_pic'])): ?>
                                    <img src="<?php echo displayValue($profile['profile_pic']); ?>" 
                                         alt="Current Profile Picture" 
                                         class="rounded-circle img-thumbnail mb-3"
                                         style="width: 150px; height: 150px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="profile_pic" class="form-label">Change Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                                    <div class="form-text">Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG</div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo displayValue($profile['first_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter your first name</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo displayValue($profile['last_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter your last name</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="dob" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="dob" name="dob" 
                                           value="<?php echo displayValue($profile['dob']); ?>" required>
                                    <div class="invalid-feedback">Please enter your date of birth</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo $profile['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $profile['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your gender</div>
                                </div>

                                <!-- Religious Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="religion" class="form-label">Religion *</label>
                                    <input type="text" class="form-control" id="religion" name="religion" 
                                           value="<?php echo displayValue($profile['religion']); ?>" required>
                                    <div class="invalid-feedback">Please enter your religion</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="caste" class="form-label">Caste</label>
                                    <input type="text" class="form-control" id="caste" name="caste" 
                                           value="<?php echo displayValue($profile['caste']); ?>">
                                </div>

                                <!-- Professional Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="occupation" class="form-label">Occupation *</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation" 
                                           value="<?php echo displayValue($profile['occupation']); ?>" required>
                                    <div class="invalid-feedback">Please enter your occupation</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="income" class="form-label">Annual Income</label>
                                    <input type="text" class="form-control" id="income" name="income" 
                                           value="<?php echo displayValue($profile['income']); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="education" class="form-label">Education</label>
                                    <input type="text" class="form-control" id="education" name="education" 
                                           value="<?php echo displayValue($profile['education']); ?>">
                                </div>

                                <!-- Physical Attributes -->
                                <div class="col-md-6 mb-3">
                                    <label for="marital_status" class="form-label">Marital Status</label>
                                    <select class="form-select" id="marital_status" name="marital_status">
                                        <option value="">Select Status</option>
                                        <option value="Never Married" <?php echo $profile['marital_status'] === 'Never Married' ? 'selected' : ''; ?>>Never Married</option>
                                        <option value="Divorced" <?php echo $profile['marital_status'] === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="Widowed" <?php echo $profile['marital_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="height" class="form-label">Height (in cm)</label>
                                    <input type="number" step="0.01" class="form-control" id="height" name="height" 
                                           value="<?php echo displayValue($profile['height']); ?>">
                                </div>

                                <!-- Location Information -->
                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo displayValue($profile['city']); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?php echo displayValue($profile['state']); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" 
                                           value="<?php echo displayValue($profile['country']); ?>">
                                </div>

                                <!-- About Me -->
                                <div class="col-12 mb-3">
                                    <label for="about_me" class="form-label">About Me</label>
                                    <textarea class="form-control" id="about_me" name="about_me" rows="4"><?php echo displayValue($profile['about_me']); ?></textarea>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                                <a href="dashboard.php" class="btn btn-secondary ms-2">Cancel</a>
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
