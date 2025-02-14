<?php
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'index.php'):
?>
<footer class="footer mt-auto py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>About Us</h5>
                <p>Indian Matrimony is a leading matrimonial service helping people find their perfect life partner.</p>
            </div>
            <div class="col-md-4">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="about.php" class="text-decoration-none">About Us</a></li>
                    <li><a href="contact.php" class="text-decoration-none">Contact Us</a></li>
                    <li><a href="privacy.php" class="text-decoration-none">Privacy Policy</a></li>
                    <li><a href="terms.php" class="text-decoration-none">Terms & Conditions</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Contact Us</h5>
                <ul class="list-unstyled">
                    <li><i class="bi bi-envelope me-2"></i>support@indianmatrimony.com</li>
                    <li><i class="bi bi-phone me-2"></i>+91 1234567890</li>
                    <li><i class="bi bi-geo-alt me-2"></i>123, Main Street, Mumbai, India</li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="text-center">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> Indian Matrimony. All rights reserved.</small>
        </div>
    </div>
</footer>
<?php endif; ?>
