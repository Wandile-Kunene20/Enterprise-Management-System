</div> <!-- Close container div -->
</main> <!-- Close main div -->

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>EnterprisePro</h3>
                <p>A comprehensive enterprise management solution for modern businesses.</p>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <a href="#" style="color: white; font-size: 1.25rem;">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" style="color: white; font-size: 1.25rem;">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" style="color: white; font-size: 1.25rem;">
                        <i class="fab fa-linkedin"></i>
                    </a>
                    <a href="#" style="color: white; font-size: 1.25rem;">
                        <i class="fab fa-github"></i>
                    </a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="../modules/admin/users.php">User Management</a></li>
                        <li><a href="../modules/admin/reports.php">Reports</a></li>
                    <?php elseif ($user_role === 'manager'): ?>
                        <li><a href="../modules/manager/projects.php">Projects</a></li>
                        <li><a href="../modules/manager/teams.php">Team Management</a></li>
                    <?php else: ?>
                        <li><a href="../modules/employee/tasks.php">My Tasks</a></li>
                        <li><a href="../modules/employee/profile.php">My Profile</a></li>
                    <?php endif; ?>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Documentation</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contact</h3>
                <p><i class="fas fa-envelope"></i> support@enterprisepro.com</p>
                <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                <p><i class="fas fa-map-marker-alt"></i> 123 Business St, Suite 100</p>
                <p>New York, NY 10001</p>
            </div>
            
            <div class="footer-section">
                <h3>System Status</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Database:</span>
                        <span style="color: #28a745;">Online</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Services:</span>
                        <span style="color: #28a745;">All Operational</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Uptime:</span>
                        <span>99.9%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Users Online:</span>
                        <span><?php echo rand(5, 50); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <?php echo date('Y'); ?> EnterprisePro Management System. All rights reserved. | 
                Version 1.0.0 | 
                <span style="color: rgba(255, 255, 255, 0.7);">
                    Logged in as: <?php echo ucfirst($user_role); ?> | 
                    Last login: <?php echo date('M d, Y H:i'); ?>
                </span>
            </p>
        </div>
    </div>
</footer>

<script src="../js/main.js"></script>
<script src="../js/validation.js"></script>
</body>
</html>