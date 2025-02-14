<?php
require_once 'config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indian Matrimony - Find Your Perfect Life Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/">Indian Matrimony</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-white text-center">
        <div class="container">
            <h1 class="display-4">Find Your Perfect Life Partner</h1>
            <p class="lead">Join millions of happy couples who found their soulmate here</p>
            
            <!-- Search Form -->
            <div class="search-form bg-white p-4 rounded shadow-lg">
                <form action="search.php" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="gender" class="form-select">
                            <option value="">I'm looking for</option>
                            <option value="female">Bride</option>
                            <option value="male">Groom</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="religion" class="form-select">
                            <option value="">Religion</option>
                            <option value="hindu">Hindu</option>
                            <option value="muslim">Muslim</option>
                            <option value="christian">Christian</option>
                            <option value="sikh">Sikh</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="mother_tongue" class="form-select">
                            <option value="">Mother Tongue</option>
                            <option value="hindi">Hindi</option>
                            <option value="tamil">Tamil</option>
                            <option value="telugu">Telugu</option>
                            <option value="malayalam">Malayalam</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section class="features py-5">
        <div class="container">
            <h2 class="text-center mb-5">Find your Special Someone</h2>
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="feature-item">
                        <img src="assets/images/signup.svg" alt="Sign Up" class="mb-3">
                        <h3>Sign Up</h3>
                        <p>Create your detailed profile</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-item">
                        <img src="assets/images/connect.svg" alt="Connect" class="mb-3">
                        <h3>Connect</h3>
                        <p>Connect with matching profiles</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-item">
                        <img src="assets/images/interact.svg" alt="Interact" class="mb-3">
                        <h3>Interact</h3>
                        <p>Start your journey together</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Success Stories -->
    <section class="success-stories py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Matrimony Service with Millions of Success Stories</h2>
            <div class="row">
                <!-- Success Story Cards will be dynamically populated from database -->
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <h5>About Us</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">About Company</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Privacy & Terms</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Cookie Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Help & Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">24x7 Live help</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Feedback</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Download App</h5>
                    <div class="app-downloads">
                        <a href="#" class="btn btn-outline-light mb-2">
                            <i class="fab fa-google-play"></i> Google Play
                        </a>
                        <a href="#" class="btn btn-outline-light">
                            <i class="fab fa-apple"></i> App Store
                        </a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2025 Indian Matrimony. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-kit-code.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
