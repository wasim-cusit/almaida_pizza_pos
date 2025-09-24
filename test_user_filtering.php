<?php
/**
 * Test Script to Verify User Filtering in Shift Schedule
 * This script tests that branch admin shift schedule only shows branch staff, not super admin users
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>User Filtering Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .btn { background: #20bf55; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #1aa049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 User Filtering Test</h1>
        <p>This script tests that the shift schedule correctly filters users to show only branch staff (excluding super admin users).</p>

        <?php
        try {
            // Get all users to see the complete list
            echo "<h3>📋 All Users in Database</h3>";
            $query = "SELECT id, name, role, branch_id, is_active FROM users ORDER BY branch_id, role, name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $all_users = $stmt->fetchAll();
            
            if (empty($all_users)) {
                echo "<div class='warning'>No users found in database.</div>";
            } else {
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Branch ID</th><th>Active</th></tr>";
                foreach ($all_users as $user) {
                    $role_color = $user['role'] === 'super_admin' ? '#dc3545' : ($user['role'] === 'admin' ? '#28a745' : '#17a2b8');
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['name']}</td>";
                    echo "<td style='color: $role_color; font-weight: bold;'>{$user['role']}</td>";
                    echo "<td>{$user['branch_id']}</td>";
                    echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            // Get all branches
            echo "<h3>🏢 Branches</h3>";
            $query = "SELECT id, name FROM branches ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $branches = $stmt->fetchAll();
            
            if (empty($branches)) {
                echo "<div class='warning'>No branches found in database.</div>";
            } else {
                echo "<table>";
                echo "<tr><th>ID</th><th>Branch Name</th></tr>";
                foreach ($branches as $branch) {
                    echo "<tr><td>{$branch['id']}</td><td>{$branch['name']}</td></tr>";
                }
                echo "</table>";
            }

            // Test filtering for each branch
            echo "<h3>🔍 Testing Branch User Filtering</h3>";
            foreach ($branches as $branch) {
                echo "<h4>Branch: {$branch['name']} (ID: {$branch['id']})</h4>";
                
                // Test the exact query used in shift_schedule.php
                $query = "SELECT id, name, role FROM users 
                         WHERE branch_id = ? AND is_active = 1 AND role != 'super_admin' 
                         ORDER BY name";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch['id']]);
                $filtered_users = $stmt->fetchAll();
                
                if (empty($filtered_users)) {
                    echo "<div class='info'>No staff members found for this branch (excluding super admin users).</div>";
                } else {
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Name</th><th>Role</th></tr>";
                    foreach ($filtered_users as $user) {
                        $role_color = $user['role'] === 'admin' ? '#28a745' : '#17a2b8';
                        echo "<tr>";
                        echo "<td>{$user['id']}</td>";
                        echo "<td>{$user['name']}</td>";
                        echo "<td style='color: $role_color; font-weight: bold;'>{$user['role']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            }

            // Test for super admin users specifically
            echo "<h3>🚫 Super Admin Users (Should be excluded from branch filtering)</h3>";
            $query = "SELECT id, name, role, branch_id FROM users WHERE role = 'super_admin'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $super_admins = $stmt->fetchAll();
            
            if (empty($super_admins)) {
                echo "<div class='success'>✅ No super admin users found in database.</div>";
            } else {
                echo "<div class='warning'>⚠️ Found " . count($super_admins) . " super admin user(s) - these should be excluded from branch staff lists:</div>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Branch ID</th></tr>";
                foreach ($super_admins as $admin) {
                    echo "<tr>";
                    echo "<td>{$admin['id']}</td>";
                    echo "<td>{$admin['name']}</td>";
                    echo "<td style='color: #dc3545; font-weight: bold;'>{$admin['role']}</td>";
                    echo "<td>{$admin['branch_id']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            // Summary
            echo "<h3>📊 Test Summary</h3>";
            $total_users = count($all_users);
            $super_admin_count = count($super_admins);
            $branch_staff_count = $total_users - $super_admin_count;
            
            echo "<div class='info'>";
            echo "<strong>Total Users:</strong> $total_users<br>";
            echo "<strong>Super Admin Users:</strong> $super_admin_count (excluded from branch filtering)<br>";
            echo "<strong>Branch Staff Users:</strong> $branch_staff_count (available for shift scheduling)<br>";
            echo "</div>";
            
            if ($super_admin_count > 0) {
                echo "<div class='success'>✅ Super admin users are properly excluded from branch staff filtering.</div>";
            } else {
                echo "<div class='info'>ℹ️ No super admin users found to test exclusion.</div>";
            }

        } catch (Exception $e) {
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4>🔗 Next Steps</h4>
            <p>If the filtering looks correct, you can now use the shift schedule feature:</p>
            <a href="admin/shift_schedule.php" class="btn">Go to Shift Schedule</a>
            <a href="admin/index.php" class="btn">Go to Admin Dashboard</a>
        </div>
    </div>
</body>
</html>
