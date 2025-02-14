<?php
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
$matches = [];
$user_profile = null;

// Get filter parameters
$min_age = isset($_GET['min_age']) ? (int)$_GET['min_age'] : null;
$max_age = isset($_GET['max_age']) ? (int)$_GET['max_age'] : null;
$religion = isset($_GET['religion']) ? trim($_GET['religion']) : null;
$caste = isset($_GET['caste']) ? trim($_GET['caste']) : null;
$marital_status = isset($_GET['marital_status']) ? trim($_GET['marital_status']) : null;
$education = isset($_GET['education']) ? trim($_GET['education']) : null;
$occupation = isset($_GET['occupation']) ? trim($_GET['occupation']) : null;
$city = isset($_GET['city']) ? trim($_GET['city']) : null;
$state = isset($_GET['state']) ? trim($_GET['state']) : null;

try {
    $conn = connectDB();
    
    // Get user's profile
    $stmt = mysqli_prepare($conn, "
        SELECT p.*, u.email, u.is_premium
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
    $user_profile = mysqli_fetch_assoc($result);
    
    // If profile doesn't exist, redirect to create profile
    if (!$user_profile) {
        header("Location: profile-create.php");
        exit();
    }
    
    // Get matches based on filters
    $base_query = "
        SELECT DISTINCT p.*, u.email, u.is_premium, u.is_verified,
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as age
        FROM profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id != ? AND p.gender != ?
    ";

    $params = [$user_id, $user_profile['gender']];
    $types = "is";

    if ($min_age !== null) {
        $base_query .= " AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) >= ?";
        $params[] = $min_age;
        $types .= "i";
    }

    if ($max_age !== null) {
        $base_query .= " AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) <= ?";
        $params[] = $max_age;
        $types .= "i";
    }

    if ($religion !== null) {
        $base_query .= " AND p.religion = ?";
        $params[] = $religion;
        $types .= "s";
    }

    if ($caste !== null) {
        $base_query .= " AND p.caste = ?";
        $params[] = $caste;
        $types .= "s";
    }

    if ($marital_status !== null) {
        $base_query .= " AND p.marital_status = ?";
        $params[] = $marital_status;
        $types .= "s";
    }

    if ($education !== null) {
        $base_query .= " AND p.education = ?";
        $params[] = $education;
        $types .= "s";
    }

    if ($occupation !== null) {
        $base_query .= " AND p.occupation = ?";
        $params[] = $occupation;
        $types .= "s";
    }

    if ($city !== null) {
        $base_query .= " AND p.city = ?";
        $params[] = $city;
        $types .= "s";
    }

    if ($state !== null) {
        $base_query .= " AND p.state = ?";
        $params[] = $state;
        $types .= "s";
    }

    $base_query .= " ORDER BY u.is_premium DESC, p.created_at DESC LIMIT 20";

    $stmt = mysqli_prepare($conn, $base_query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    $matches = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Get distinct values for filters
    $religions = [];
    $castes = [];
    $cities = [];
    $states = [];
    $educations = [];
    $occupations = [];
    
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT religion FROM profiles WHERE religion IS NOT NULL AND religion != ''
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $religions[] = $row['religion'];
    }
    
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT caste FROM profiles WHERE caste IS NOT NULL AND caste != ''
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $castes[] = $row['caste'];
    }
    
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT city FROM profiles WHERE city IS NOT NULL AND city != ''
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $cities[] = $row['city'];
    }
    
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT state FROM profiles WHERE state IS NOT NULL AND state != ''
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $states[] = $row['state'];
    }
    
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT education FROM profiles WHERE education IS NOT NULL AND education != ''
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $educations[] = $row['education'];
    }
    
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT occupation FROM profiles WHERE occupation IS NOT NULL AND occupation != ''
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $occupations[] = $row['occupation'];
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
    <title>Matches - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Filters</h5>
                        <form method="GET" action="matches.php" class="needs-validation" novalidate>
                            <!-- Age Range -->
                            <div class="mb-3">
                                <label class="form-label">Age Range</label>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="number" class="form-control" name="min_age" placeholder="Min" min="18" max="100" value="<?php echo $min_age; ?>">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control" name="max_age" placeholder="Max" min="18" max="100" value="<?php echo $max_age; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Religion -->
                            <div class="mb-3">
                                <label class="form-label">Religion</label>
                                <select class="form-select" name="religion">
                                    <option value="">Any</option>
                                    <?php foreach ($religions as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $religion === $r ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Caste -->
                            <div class="mb-3">
                                <label class="form-label">Caste</label>
                                <select class="form-select" name="caste">
                                    <option value="">Any</option>
                                    <?php foreach ($castes as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $caste === $c ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Marital Status -->
                            <div class="mb-3">
                                <label class="form-label">Marital Status</label>
                                <select class="form-select" name="marital_status">
                                    <option value="">Any</option>
                                    <option value="Never Married" <?php echo $marital_status === 'Never Married' ? 'selected' : ''; ?>>Never Married</option>
                                    <option value="Divorced" <?php echo $marital_status === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo $marital_status === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>

                            <!-- Education -->
                            <div class="mb-3">
                                <label class="form-label">Education</label>
                                <select class="form-select" name="education">
                                    <option value="">Any</option>
                                    <?php foreach ($educations as $e): ?>
                                        <option value="<?php echo htmlspecialchars($e); ?>" <?php echo $education === $e ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($e); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Occupation -->
                            <div class="mb-3">
                                <label class="form-label">Occupation</label>
                                <select class="form-select" name="occupation">
                                    <option value="">Any</option>
                                    <?php foreach ($occupations as $o): ?>
                                        <option value="<?php echo htmlspecialchars($o); ?>" <?php echo $occupation === $o ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Location -->
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <select class="form-select" name="city">
                                    <option value="">Any</option>
                                    <?php foreach ($cities as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $city === $c ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <select class="form-select" name="state">
                                    <option value="">Any</option>
                                    <?php foreach ($states as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $state === $s ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Matches Grid -->
            <div class="col-md-9">
                <?php if (empty($matches)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No matches found with the selected filters. Try adjusting your filters to see more profiles.
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($matches as $match): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <?php if ($match['profile_pic']): ?>
                                        <img src="<?php echo htmlspecialchars($match['profile_pic']); ?>" 
                                             class="card-img-top" alt="Profile Picture"
                                             style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-secondary text-white d-flex align-items-center justify-content-center"
                                             style="height: 200px;">
                                            <i class="bi bi-person" style="font-size: 4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0">
                                                <?php echo htmlspecialchars($match['first_name'] . ' ' . $match['last_name']); ?>
                                                <?php if ($match['is_premium']): ?>
                                                    <i class="bi bi-patch-check-fill text-primary ms-1" title="Premium Member"></i>
                                                <?php endif; ?>
                                                <?php if ($match['is_verified']): ?>
                                                    <i class="bi bi-check-circle-fill text-success ms-1" title="Verified Profile"></i>
                                                <?php endif; ?>
                                            </h5>
                                            <span class="badge bg-primary"><?php echo $match['age']; ?> yrs</span>
                                        </div>
                                        
                                        <div class="small text-muted mb-3">
                                            <div><i class="bi bi-briefcase me-2"></i><?php echo htmlspecialchars($match['occupation']); ?></div>
                                            <div><i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($match['city'] . ', ' . $match['state']); ?></div>
                                            <div><i class="bi bi-book me-2"></i><?php echo htmlspecialchars($match['education']); ?></div>
                                            <div><i class="bi bi-heart me-2"></i><?php echo htmlspecialchars($match['marital_status']); ?></div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="view-profile.php?id=<?php echo $match['user_id']; ?>" class="btn btn-primary">
                                                View Profile
                                            </a>
                                            <button class="btn btn-outline-primary" onclick="sendInterest(<?php echo $match['user_id']; ?>)">
                                                <i class="bi bi-heart"></i> Send Interest
                                            </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

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

// Send interest function
function sendInterest(userId) {
    // TODO: Implement send interest functionality
    alert('Interest sending feature coming soon!');
}
</script>
