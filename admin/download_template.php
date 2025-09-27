<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Create a simple CSV template if PhpSpreadsheet is not available
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="shift_schedule_template.csv"');

// CSV Headers
echo "Employee ID,Shift Date (YYYY-MM-DD),Start Time (HH:MM),End Time (HH:MM),Shift Type,Notes,Fingerprint ID (Optional)\n";

// Sample data
echo "1,2024-01-15,09:00,17:00,full_day,Regular shift,FP001\n";
echo "2,2024-01-15,14:00,22:00,afternoon,Evening shift,FP002\n";

echo "\n";
echo "INSTRUCTIONS:\n";
echo "1. Employee ID: Must match existing employee ID in your branch\n";
echo "2. Shift Date: Format YYYY-MM-DD (e.g., 2024-01-15)\n";
echo "3. Start/End Time: Format HH:MM (e.g., 09:00, 17:30)\n";
echo "4. Shift Type: full_day, morning, afternoon, evening, night\n";
echo "5. Notes: Optional additional information\n";
echo "6. Fingerprint ID: Optional for biometric device integration\n";
echo "\n";
echo "VALID SHIFT TYPES:\n";
echo "• full_day - Full day shift\n";
echo "• morning - Morning shift\n";
echo "• afternoon - Afternoon shift\n";
echo "• evening - Evening shift\n";
echo "• night - Night shift\n";
?>
