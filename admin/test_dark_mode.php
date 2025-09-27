<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Dark Mode Test";
include 'includes/header.php';
?>

<div class="admin-section">
    <h2><i class="fas fa-palette"></i> Dark Mode & Design Consistency Test</h2>
    <p>This page tests the dark/light mode functionality and design consistency across all admin pages.</p>
    
    <div style="margin-top: 30px;">
        <h3>🌙 Dark Mode Test</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            
            <!-- Theme Toggle Test -->
            <div style="padding: 20px; background: var(--bg-secondary, #f8f9fa); border-radius: 8px; border: 1px solid var(--border-color, #dee2e6);">
                <h4><i class="fas fa-toggle-on"></i> Theme Toggle Test</h4>
                <p style="margin: 10px 0; color: var(--text-primary, #333);">Click the moon/sun icon in the top-right corner to test dark/light mode switching.</p>
                <div style="margin: 15px 0;">
                    <button id="themeToggle" style="padding: 10px 20px; background: var(--bg-tertiary, #e9ecef); border: 1px solid var(--border-color, #dee2e6); border-radius: 6px; cursor: pointer;">
                        <i id="themeIcon" class="fas fa-moon"></i> Toggle Theme
                    </button>
                </div>
            </div>
            
            <!-- Color Variables Test -->
            <div style="padding: 20px; background: var(--bg-secondary, #f8f9fa); border-radius: 8px; border: 1px solid var(--border-color, #dee2e6);">
                <h4><i class="fas fa-palette"></i> Color Variables Test</h4>
                <div style="margin: 10px 0;">
                    <div style="display: flex; align-items: center; gap: 10px; margin: 5px 0;">
                        <div style="width: 20px; height: 20px; background: var(--bg-primary, #ffffff); border: 1px solid var(--border-color, #dee2e6); border-radius: 3px;"></div>
                        <span style="color: var(--text-primary, #333);">Primary Background</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin: 5px 0;">
                        <div style="width: 20px; height: 20px; background: var(--bg-secondary, #f8f9fa); border: 1px solid var(--border-color, #dee2e6); border-radius: 3px;"></div>
                        <span style="color: var(--text-primary, #333);">Secondary Background</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin: 5px 0;">
                        <div style="width: 20px; height: 20px; background: var(--text-primary, #333333); border: 1px solid var(--border-color, #dee2e6); border-radius: 3px;"></div>
                        <span style="color: var(--text-primary, #333);">Primary Text</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin: 5px 0;">
                        <div style="width: 20px; height: 20px; background: var(--text-secondary, #666666); border: 1px solid var(--border-color, #dee2e6); border-radius: 3px;"></div>
                        <span style="color: var(--text-primary, #333);">Secondary Text</span>
                    </div>
                </div>
            </div>
            
            <!-- Component Test -->
            <div style="padding: 20px; background: var(--bg-secondary, #f8f9fa); border-radius: 8px; border: 1px solid var(--border-color, #dee2e6);">
                <h4><i class="fas fa-cubes"></i> Component Test</h4>
                <div style="margin: 15px 0;">
                    <button style="padding: 8px 16px; background: #20bf55; color: white; border: none; border-radius: 6px; margin: 5px; cursor: pointer;">Success Button</button>
                    <button style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 6px; margin: 5px; cursor: pointer;">Danger Button</button>
                    <button style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 6px; margin: 5px; cursor: pointer;">Secondary Button</button>
                </div>
                <div style="margin: 15px 0;">
                    <input type="text" placeholder="Test input field" style="padding: 8px 12px; border: 1px solid var(--border-color, #dee2e6); border-radius: 6px; background: var(--bg-primary, #ffffff); color: var(--text-primary, #333); width: 100%;">
                </div>
            </div>
            
            <!-- Table Test -->
            <div style="padding: 20px; background: var(--bg-secondary, #f8f9fa); border-radius: 8px; border: 1px solid var(--border-color, #dee2e6);">
                <h4><i class="fas fa-table"></i> Table Test</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px; background: var(--bg-primary, #ffffff); border: 1px solid var(--border-color, #dee2e6);">
                    <thead>
                        <tr style="background: var(--bg-tertiary, #e9ecef);">
                            <th style="padding: 12px; border-bottom: 1px solid var(--border-color, #dee2e6); color: var(--text-primary, #333);">Name</th>
                            <th style="padding: 12px; border-bottom: 1px solid var(--border-color, #dee2e6); color: var(--text-primary, #333);">Status</th>
                            <th style="padding: 12px; border-bottom: 1px solid var(--border-color, #dee2e6); color: var(--text-primary, #333);">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-color, #dee2e6);">
                            <td style="padding: 12px; color: var(--text-primary, #333);">Test Item 1</td>
                            <td style="padding: 12px; color: var(--text-primary, #333);"><span style="background: #20bf55; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;">Active</span></td>
                            <td style="padding: 12px; color: var(--text-primary, #333);">$100.00</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color, #dee2e6);">
                            <td style="padding: 12px; color: var(--text-primary, #333);">Test Item 2</td>
                            <td style="padding: 12px; color: var(--text-primary, #333);"><span style="background: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;">Pending</span></td>
                            <td style="padding: 12px; color: var(--text-primary, #333);">$200.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: var(--bg-secondary, #e8f5e8); border-radius: 8px; border-left: 4px solid #4caf50;">
        <h4><i class="fas fa-check-circle"></i> Testing Instructions:</h4>
        <ol style="margin: 15px 0; padding-left: 20px; color: var(--text-primary, #333);">
            <li><strong>Theme Toggle:</strong> Click the moon/sun icon in the top-right corner multiple times to test switching between light and dark modes</li>
            <li><strong>Persistence:</strong> Refresh the page to ensure the theme preference is saved and restored</li>
            <li><strong>Color Consistency:</strong> Verify that all colors change appropriately in both light and dark modes</li>
            <li><strong>Component Visibility:</strong> Ensure all buttons, inputs, and table elements are clearly visible in both modes</li>
            <li><strong>Navigation:</strong> Test the sidebar navigation in both light and dark modes</li>
            <li><strong>Mobile Test:</strong> Test the hamburger menu and responsive design in both themes</li>
        </ol>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: var(--bg-secondary, #fff3e0); border-radius: 8px; border-left: 4px solid #ff9800;">
        <h4><i class="fas fa-bug"></i> Known Issues & Fixes:</h4>
        <ul style="margin: 15px 0; padding-left: 20px; color: var(--text-primary, #333);">
            <li>✅ <strong>Fixed:</strong> All admin pages now use shared header/footer components</li>
            <li>✅ <strong>Fixed:</strong> Consistent sidebar navigation across all pages</li>
            <li>✅ <strong>Fixed:</strong> Proper dark mode CSS variables implementation</li>
            <li>✅ <strong>Fixed:</strong> Mobile responsive design with hamburger menu</li>
            <li>✅ <strong>Fixed:</strong> Theme persistence using localStorage</li>
            <li>✅ <strong>Fixed:</strong> Smooth transitions between light and dark modes</li>
        </ul>
    </div>
</div>

<script>
// Additional theme toggle functionality for testing
document.addEventListener('DOMContentLoaded', function() {
    const testThemeToggle = document.getElementById('themeToggle');
    const testThemeIcon = document.getElementById('themeIcon');
    const body = document.body;
    
    testThemeToggle.addEventListener('click', function() {
        const currentTheme = body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        body.setAttribute('data-theme', newTheme);
        if (newTheme === 'dark') {
            testThemeIcon.className = 'fas fa-sun';
            testThemeToggle.title = 'Switch to Light Mode';
        } else {
            testThemeIcon.className = 'fas fa-moon';
            testThemeToggle.title = 'Switch to Dark Mode';
        }
        localStorage.setItem('theme', newTheme);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
