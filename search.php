<?php
require_once 'config/database.php';
session_start();

$conn = connectDB();
$where_conditions = [];
$params = [];
$types = "";

// Build search query based on filters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['gender'])) {
        $where_conditions[] = "p.gender = ?";
        $params[] = $_GET['gender'];
        $types .= "s";
    }
    
    if (!empty($_GET['religion'])) {
        $where_conditions[] = "p.religion = ?";
        $params[] = $_GET['religion'];
        $types .= "s";
    }
    
    if (!empty($_GET['caste'])) {
        $where_conditions[] = "p.caste = ?";
        $params[] = $_GET['caste'];
        $types .= "s";
    }
    
    if (!empty($_GET['age_min'])) {
        $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) >= ?";
        $params[] = $_GET['age_min'];
        $types .= "i";
    }
    
    if (!empty($_GET['age_max'])) {
        $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) <= ?";
        $params[] = $_GET['age_max'];
        $types .= "i";
    }
    
    if (!empty($_GET['marital_status'])) {
        $where_conditions[] = "p.marital_status = ?";
        $params[] = $_GET['marital_status'];
        $types .= "s";
    }

    if (!empty($_GET['education'])) {
        $where_conditions[] = "p.education = ?";
        $params[] = $_GET['education'];
        $types .= "s";
    }

    if (!empty($_GET['occupation'])) {
        $where_conditions[] = "p.occupation = ?";
        $params[] = $_GET['occupation'];
        $types .= "s";
    }

    if (!empty($_GET['city'])) {
        $where_conditions[] = "p.city = ?";
        $params[] = $_GET['city'];
        $types .= "s";
    }

    if (!empty($_GET['state'])) {
        $where_conditions[] = "p.state = ?";
        $params[] = $_GET['state'];
        $types .= "s";
    }
}

// Base query
$query = "
    SELECT 
        p.*,
        u.email,
        u.is_premium,
        u.is_verified,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as age
    FROM profiles p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN admin_users au ON u.id = au.user_id
    WHERE au.id IS NULL
    AND u.show_profile = 1
";

// Add where conditions if any
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

// Add pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$query .= " ORDER BY u.is_verified DESC, u.is_premium DESC, p.created_at DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $per_page;
$params[] = $offset;

// Execute query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profiles = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM profiles p 
    JOIN users u ON u.id = p.user_id 
    LEFT JOIN admin_users au ON u.id = au.user_id
    WHERE au.id IS NULL
    AND u.show_profile = 1
";
if (!empty($where_conditions)) {
    $count_query .= " AND " . implode(" AND ", $where_conditions);
}
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    // Remove the last two parameters (LIMIT and OFFSET)
    array_pop($params);
    array_pop($params);
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, substr($types, 0, -2), ...$params);
    }
}
mysqli_stmt_execute($count_stmt);
$result = mysqli_stmt_get_result($count_stmt);
$total_profiles = mysqli_fetch_assoc($result)['total'];
$total_pages = ceil($total_profiles / $per_page);

// Get filter options
$religions = [];
$castes = [];
$educations = [];
$occupations = [];
$cities = [];
$states = [];

$stmt = mysqli_prepare($conn, "SELECT DISTINCT religion FROM profiles WHERE religion IS NOT NULL AND religion != ''");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $religions[] = $row['religion'];
}

$stmt = mysqli_prepare($conn, "SELECT DISTINCT caste FROM profiles WHERE caste IS NOT NULL AND caste != ''");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $castes[] = $row['caste'];
}

$stmt = mysqli_prepare($conn, "SELECT DISTINCT education FROM profiles WHERE education IS NOT NULL AND education != ''");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $educations[] = $row['education'];
}

$stmt = mysqli_prepare($conn, "SELECT DISTINCT occupation FROM profiles WHERE occupation IS NOT NULL AND occupation != ''");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $occupations[] = $row['occupation'];
}

$stmt = mysqli_prepare($conn, "SELECT DISTINCT city FROM profiles WHERE city IS NOT NULL AND city != ''");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $cities[] = $row['city'];
}

$stmt = mysqli_prepare($conn, "SELECT DISTINCT state FROM profiles WHERE state IS NOT NULL AND state != ''");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $states[] = $row['state'];
}

closeDB($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Profiles - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'profile_not_found'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                The profile you're trying to view is either not found or currently under verification.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search Filters -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Search Profiles</h5>
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Any</option>
                                    <option value="Male" <?php echo isset($_GET['gender']) && $_GET['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo isset($_GET['gender']) && $_GET['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Religion</label>
                                <select name="religion" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($religions as $religion): ?>
                                        <option value="<?php echo htmlspecialchars($religion, ENT_QUOTES, 'UTF-8'); ?>" 
                                                <?php echo isset($_GET['religion']) && $_GET['religion'] === $religion ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($religion, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Caste</label>
                                <select name="caste" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($castes as $caste): ?>
                                        <option value="<?php echo htmlspecialchars($caste, ENT_QUOTES, 'UTF-8'); ?>" 
                                                <?php echo isset($_GET['caste']) && $_GET['caste'] === $caste ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($caste, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Age Range</label>
                                <div class="d-flex">
                                    <input type="number" name="age_min" class="form-control" placeholder="Min" 
                                           value="<?php echo $_GET['age_min'] ?? ''; ?>" min="18" max="100">
                                    <input type="number" name="age_max" class="form-control ms-2" placeholder="Max" 
                                           value="<?php echo $_GET['age_max'] ?? ''; ?>" min="18" max="100">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-select">
                                    <option value="">Any</option>
                                    <option value="Never Married" <?php echo isset($_GET['marital_status']) && $_GET['marital_status'] === 'Never Married' ? 'selected' : ''; ?>>Never Married</option>
                                    <option value="Divorced" <?php echo isset($_GET['marital_status']) && $_GET['marital_status'] === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo isset($_GET['marital_status']) && $_GET['marital_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Education</label>
                                <select name="education" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($educations as $education): ?>
                                        <option value="<?php echo htmlspecialchars($education, ENT_QUOTES, 'UTF-8'); ?>" 
                                                <?php echo isset($_GET['education']) && $_GET['education'] === $education ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($education, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Occupation</label>
                                <select name="occupation" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($occupations as $occupation): ?>
                                        <option value="<?php echo htmlspecialchars($occupation, ENT_QUOTES, 'UTF-8'); ?>" 
                                                <?php echo isset($_GET['occupation']) && $_GET['occupation'] === $occupation ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($occupation, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Location</label>
                                <div class="row">
                                    <div class="col-6">
                                        <select name="city" class="form-select">
                                            <option value="">Any City</option>
                                            <?php foreach ($cities as $city): ?>
                                                <option value="<?php echo htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?>" 
                                                        <?php echo isset($_GET['city']) && $_GET['city'] === $city ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select name="state" class="form-select">
                                            <option value="">Any State</option>
                                            <?php foreach ($states as $state): ?>
                                                <option value="<?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>" 
                                                        <?php echo isset($_GET['state']) && $_GET['state'] === $state ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-search me-2"></i>Search Profiles
                                </button>
                                <a href="search.php" class="btn btn-outline-secondary px-4 ms-2">
                                    <i class="bi bi-x-circle me-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <?php if (!empty($profiles)): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Search Results (<?php echo $total_profiles; ?> profiles found)</h5>
            </div>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($profiles as $profile): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($profile['profile_pic']): ?>
                                <img src="<?php echo htmlspecialchars($profile['profile_pic'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="card-img-top" alt="Profile Picture" 
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <span style="font-size: 4rem;">
                                        <?php echo strtoupper(substr($profile['first_name'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($profile['is_premium']): ?>
                                            <i class="bi bi-star-fill text-warning" title="Premium Member"></i>
                                        <?php endif; ?>
                                    </h5>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($profile['age'], ENT_QUOTES, 'UTF-8'); ?> yrs
                                    </span>
                                </div>
                                
                                <ul class="list-unstyled mb-3">
                                    <?php if ($profile['occupation']): ?>
                                        <li>
                                            <i class="bi bi-briefcase me-2"></i>
                                            <?php echo htmlspecialchars($profile['occupation'], ENT_QUOTES, 'UTF-8'); ?>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($profile['education']): ?>
                                        <li>
                                            <i class="bi bi-book me-2"></i>
                                            <?php echo htmlspecialchars($profile['education'], ENT_QUOTES, 'UTF-8'); ?>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($profile['city'] && $profile['state']): ?>
                                        <li>
                                            <i class="bi bi-geo-alt me-2"></i>
                                            <?php echo htmlspecialchars($profile['city'] . ', ' . $profile['state'], ENT_QUOTES, 'UTF-8'); ?>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                
                                <div class="d-flex gap-2">
                                    <a href="view-profile.php?id=<?php echo $profile['user_id']; ?>" 
                                       class="btn btn-primary flex-grow-1">View Profile</a>
                                    <button type="button" 
                                            class="btn btn-outline-primary send-interest" 
                                            data-user-id="<?php echo $profile['user_id']; ?>">
                                        <i class="bi bi-heart"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle me-2"></i>
                    No profiles found matching your search criteria. Try adjusting your filters to see more results.
                </div>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Next
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Send interest function
    function sendInterest(userId) {
        // TODO: Implement send interest functionality
        alert('Interest sending feature coming soon!');
    }

    // Grid/List view toggle
    document.querySelectorAll('[data-bs-toggle="grid-view"]').forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;
            const target = document.querySelector(this.dataset.bsTarget);
            
            // Update buttons
            document.querySelectorAll('[data-bs-toggle="grid-view"]').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            // Update view
            if (view === 'grid') {
                target.classList.remove('list-view');
                document.querySelectorAll('.col-md-3').forEach(col => {
                    col.classList.remove('col-md-12');
                });
            } else {
                target.classList.add('list-view');
                document.querySelectorAll('.col-md-3').forEach(col => {
                    col.classList.add('col-md-12');
                });
            }
        });
    });
    </script>
</body>
</html>
