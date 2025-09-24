<?php
/**
 * Database Migration Runner
 * Run this script to update the database with branch integration
 */

require_once 'config/database.php';

echo "<h2>Database Migration: Branch Integration</h2>";
echo "<p>Running database migration...</p>";

try {
    // Read the migration SQL file
    $sql = file_get_contents('database_branch_integration.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $db->exec($statement);
            $success_count++;
            echo "<p style='color: green;'>✓ " . substr($statement, 0, 50) . "...</p>";
        } catch (Exception $e) {
            $error_count++;
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>Migration Complete!</h3>";
    echo "<p>Successful statements: $success_count</p>";
    echo "<p>Errors: $error_count</p>";
    
    if ($error_count === 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ Database migration completed successfully!</p>";
        echo "<p>You can now:</p>";
        echo "<ul>";
        echo "<li>Create branches in the super admin panel</li>";
        echo "<li>Assign users to specific branches</li>";
        echo "<li>Manage branch-specific stock and users</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ Migration completed with some errors. Please check the errors above.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Migration failed: " . $e->getMessage() . "</p>";
}

echo "<p><a href='super_admin/index.php'>Go to Super Admin Panel</a></p>";
?>
