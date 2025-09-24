<?php
/**
 * Database Migration Runner for Revenue Reconciliation and Shift Schedule Features
 * 
 * This script will create all the necessary tables and data for the new features.
 * Run this once after uploading the new files.
 */

require_once 'config/database.php';

echo "<h2>Database Migration: Revenue Reconciliation & Shift Schedule Features</h2>";
echo "<pre>";

try {
    // Read the migration SQL file
    $sql_file = 'database_revenue_shift_management.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    echo "Found " . count($statements) . " SQL statements to execute...\n\n";
    
    $db->beginTransaction();
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $index => $statement) {
        try {
            if (empty(trim($statement))) continue;
            
            // Add back the semicolon
            $statement .= ';';
            
            echo "Executing statement " . ($index + 1) . "...\n";
            
            $db->exec($statement);
            $success_count++;
            
            echo "✅ Success\n";
            
        } catch (PDOException $e) {
            // Check if it's a "table already exists" error
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠️  Warning: " . $e->getMessage() . "\n";
                $success_count++; // Count as success since table/column already exists
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
                $error_count++;
            }
        }
        
        echo "\n";
    }
    
    // Check if we should commit or rollback
    if ($error_count === 0) {
        $db->commit();
        echo "🎉 Migration completed successfully!\n";
        echo "✅ $success_count statements executed successfully\n";
        
        // Test the new tables
        echo "\n🔍 Testing new tables...\n";
        
        $tables_to_test = [
            'cash_drawers',
            'payment_methods', 
            'revenue_reconciliation',
            'shift_schedules',
            'shift_attendance',
            'shift_templates',
            'shift_template_assignments',
            'labor_costs'
        ];
        
        foreach ($tables_to_test as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "✅ Table '$table' exists with $count records\n";
            } catch (PDOException $e) {
                echo "❌ Table '$table' test failed: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n🚀 You can now use the Revenue Reconciliation and Shift Schedule features!\n";
        echo "Access them from:\n";
        echo "- Branch Admin: Admin Dashboard → Revenue Reconciliation / Shift Schedule\n";
        echo "- Super Admin: Super Admin Dashboard → Revenue Reconciliation / Shift Schedule\n";
        
    } else {
        $db->rollBack();
        echo "❌ Migration failed with $error_count errors. Transaction rolled back.\n";
        echo "Please check the errors above and try again.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "Migration aborted.\n";
}

echo "</pre>";

// Add some styling
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    h2 { color: #333; border-bottom: 2px solid #20bf55; padding-bottom: 10px; }
    pre { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
          white-space: pre-wrap; word-wrap: break-word; }
</style>";

echo "<br><a href='admin/index.php' style='background: #20bf55; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Dashboard</a> ";
echo "<a href='super_admin/index.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to Super Admin Dashboard</a>";
?>
