</div>
    </div>
    
    <script>
        // Mobile sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainContent = document.getElementById('mainContent');
            
            // Toggle sidebar
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('active');
            }
            
            // Close sidebar
            function closeSidebar() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('active');
            }
            
            // Event listeners
            menuToggle.addEventListener('click', toggleSidebar);
            sidebarOverlay.addEventListener('click', closeSidebar);
            
            // Close sidebar when clicking on nav items (mobile)
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });
        });
        
        // Dark Mode functionality
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const body = document.body;
            
            // Check for saved theme preference or default to 'light' mode
            const currentTheme = localStorage.getItem('theme') || 'light';
            
            // Apply the current theme
            function applyTheme(theme) {
                body.setAttribute('data-theme', theme);
                if (theme === 'dark') {
                    themeIcon.className = 'fas fa-sun';
                    themeToggle.title = 'Switch to Light Mode';
                } else {
                    themeIcon.className = 'fas fa-moon';
                    themeToggle.title = 'Switch to Dark Mode';
                }
                localStorage.setItem('theme', theme);
            }
            
            // Initialize theme
            applyTheme(currentTheme);
            
            // Toggle theme
            themeToggle.addEventListener('click', function() {
                const currentTheme = body.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                applyTheme(newTheme);
                
                // Add a smooth transition effect
                body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                setTimeout(() => {
                    body.style.transition = '';
                }, 300);
            });
            
            // Auto theme based on system preference (optional)
            if (localStorage.getItem('theme') === null) {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                applyTheme(prefersDark ? 'dark' : 'light');
            }
        });
    </script>
</body>
</html>