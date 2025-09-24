        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
        
        // Mobile sidebar toggle
        if (window.innerWidth <= 768) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                sidebarOverlay.classList.toggle('show');
            });
            
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            }
        });

        // AJAX Navigation
        const navLinks = document.querySelectorAll('.nav-link[data-page]');
        const content = document.querySelector('.content');
        const loadingIndicator = document.getElementById('loadingIndicator');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Check if it's a normal click (not Ctrl+click or middle click)
                if (e.ctrlKey || e.metaKey || e.button === 1) {
                    // Allow normal navigation for Ctrl+click, Cmd+click, or middle click
                    return;
                }
                
                e.preventDefault();
                
                const page = this.getAttribute('data-page');
                const href = this.getAttribute('href');
                
                // Update active nav link
                navLinks.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                // Show loading
                loadingIndicator.classList.add('show');
                
                // Load page content via AJAX
                fetch(href)
                    .then(response => response.text())
                    .then(html => {
                        // Extract content from the response
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContent = doc.querySelector('.content');
                        
                        if (newContent) {
                            // Update page title
                            const newTitle = doc.querySelector('title');
                            if (newTitle) {
                                document.title = newTitle.textContent;
                            }
                            
                            // Update header title
                            const headerTitle = document.querySelector('.header-title');
                            const newHeaderTitle = doc.querySelector('.header-title');
                            if (newHeaderTitle && headerTitle) {
                                headerTitle.textContent = newHeaderTitle.textContent;
                            }
                            
                            // Replace content with fade effect
                            content.style.opacity = '0';
                            setTimeout(() => {
                                content.innerHTML = newContent.innerHTML;
                                content.style.opacity = '1';
                                loadingIndicator.classList.remove('show');
                                
                                // Re-initialize any page-specific scripts
                                initializePageScripts();
                                
                                // Update URL without page reload
                                history.pushState(null, null, href);
                            }, 300);
                        } else {
                            // Fallback to normal navigation
                            window.location.href = href;
                        }
                    })
                    .catch(error => {
                        console.error('Error loading page:', error);
                        loadingIndicator.classList.remove('show');
                        // Fallback to normal navigation
                        window.location.href = href;
                    });
            });
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(e) {
            // Reload the current page when back/forward is used
            window.location.reload();
        });

        // Initialize page-specific scripts
        function initializePageScripts() {
            // Add any page-specific initialization here
            // This function will be called after AJAX content loads
            
            // Re-initialize any event listeners for new content
            const newButtons = document.querySelectorAll('.btn');
            newButtons.forEach(btn => {
                if (!btn.hasAttribute('data-initialized')) {
                    btn.setAttribute('data-initialized', 'true');
                    // Add any button-specific event listeners here
                }
            });
            
            // Re-initialize forms
            const newForms = document.querySelectorAll('form');
            newForms.forEach(form => {
                if (!form.hasAttribute('data-initialized')) {
                    form.setAttribute('data-initialized', 'true');
                    form.addEventListener('submit', function(e) {
                        // Handle form submissions
                        e.preventDefault();
                        // Add form handling logic here
                    });
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            initializePageScripts();
            
            // Set active nav link based on current page (only if not already set by PHP)
            const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
            
            // Check if any nav link already has active class (set by PHP)
            const hasActiveLink = document.querySelector('.nav-link.active');
            
            if (!hasActiveLink) {
                // Remove active class from all nav links first
                navLinks.forEach(nav => nav.classList.remove('active'));
                
                // Try to find matching nav link
                let activeLink = null;
                
                // Direct match
                activeLink = document.querySelector(`[data-page="${currentPage}"]`);
                
                // If no direct match, try alternative mappings
                if (!activeLink) {
                    const pageMappings = {
                        'index': 'dashboard',
                        'stock_management': 'stock-management',
                        'stock_purchases': 'stock-purchases',
                        'stock_distributions': 'stock-distributions',
                        'manage_branches': 'manage-branches',
                        'notifications': 'notifications',
                        'stock_reports': 'stock-reports',
                        'suppliers': 'suppliers',
                        'warehouse_stock': 'warehouse-stock',
                        'settings': 'settings'
                    };
                    
                    const mappedPage = pageMappings[currentPage];
                    if (mappedPage) {
                        activeLink = document.querySelector(`[data-page="${mappedPage}"]`);
                    }
                }
                
                // Set active class
                if (activeLink) {
                    activeLink.classList.add('active');
                }
            }
        });

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.card, .stat-card, .action-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>
