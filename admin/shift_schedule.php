<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$page_title = "Shift Schedule";

// Get current user's branch
$branch_id = $_SESSION['branch_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

// Super admin can see all branches, others need branch assignment
if ($user_role !== 'super_admin' && !$branch_id) {
    die('No branch assigned to your account. Please contact super admin.');
}

// Ensure required columns exist in shift_schedules table
try {
    // Check and add fingerprint_id column
    $checkColumn = "SHOW COLUMNS FROM shift_schedules LIKE 'fingerprint_id'";
    $result = $db->query($checkColumn);
    if ($result->rowCount() === 0) {
        $addColumn = "ALTER TABLE shift_schedules ADD COLUMN fingerprint_id VARCHAR(50) NULL AFTER notes";
        $db->exec($addColumn);
    }
    
    // Check and add is_worker column
    $checkColumn = "SHOW COLUMNS FROM shift_schedules LIKE 'is_worker'";
    $result = $db->query($checkColumn);
    if ($result->rowCount() === 0) {
        $addColumn = "ALTER TABLE shift_schedules ADD COLUMN is_worker TINYINT(1) NOT NULL DEFAULT 0 AFTER fingerprint_id";
        $db->exec($addColumn);
    }
} catch (Exception $e) {
    // Columns might already exist or table doesn't exist yet
}

// Handle template download (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    try {
        // Try to use PhpSpreadsheet for Excel format
        require_once '../vendor/autoload.php';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="shift_schedule_template.xlsx"');
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Add headers
        $headers = [
            'Employee ID',
            'Shift Date (YYYY-MM-DD)',
            'Start Time (HH:MM)',
            'End Time (HH:MM)',
            'Shift Type',
            'Notes',
            'Fingerprint ID (Optional)'
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E3F2FD']
            ]
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        
        // Add sample data
        $sampleData = [
            ['1', '2024-01-15', '09:00', '17:00', 'full_day', 'Regular shift', 'FP001'],
            ['2', '2024-01-15', '14:00', '22:00', 'afternoon', 'Evening shift', 'FP002']
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Add instructions
        $instructions = [
            '',
            'INSTRUCTIONS:',
            '1. Employee ID: Must match existing employee ID in your branch',
            '2. Shift Date: Format YYYY-MM-DD (e.g., 2024-01-15)',
            '3. Start/End Time: Format HH:MM (e.g., 09:00, 17:30)',
            '4. Shift Type: full_day, morning, afternoon, evening, night',
            '5. Notes: Optional additional information',
            '6. Fingerprint ID: Optional for biometric device integration',
            '',
            'VALID SHIFT TYPES:',
            '• full_day - Full day shift',
            '• morning - Morning shift',
            '• afternoon - Afternoon shift', 
            '• evening - Evening shift',
            '• night - Night shift'
        ];
        
        $sheet->fromArray($instructions, null, 'A4');
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
        
    } catch (Exception $e) {
        // Fallback to CSV format
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
        exit();
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_schedule':
            $user_id = (int)$_POST['user_id'];
            $shift_date = $_POST['shift_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $shift_type = $_POST['shift_type'];
            $notes = sanitize($_POST['notes'] ?? '');
            $is_worker = (int)($_POST['is_worker'] ?? 0);
            
            try {
                $db->beginTransaction();
                
                // Check if schedule already exists
                $query = "SELECT id FROM shift_schedules WHERE user_id = ? AND shift_date = ? AND start_time = ? AND is_worker = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id, $shift_date, $start_time, $is_worker]);
                if ($stmt->fetch()) {
                    $personType = $is_worker ? 'worker' : 'employee';
                    throw new Exception("Schedule already exists for this $personType, date, and time");
                }
                
                // Create schedule
                $query = "INSERT INTO shift_schedules (branch_id, user_id, shift_date, start_time, end_time, shift_type, notes, is_worker, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $user_id, $shift_date, $start_time, $end_time, $shift_type, $notes, $is_worker, $_SESSION['user_id']]);
                
                $db->commit();
                $personType = $is_worker ? 'worker' : 'employee';
                echo json_encode(['success' => true, 'message' => "Shift schedule created successfully for $personType"]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error creating schedule: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_schedule':
            $schedule_id = (int)$_POST['schedule_id'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $shift_type = $_POST['shift_type'];
            $status = $_POST['status'];
            $notes = sanitize($_POST['notes'] ?? '');
            
            try {
                $query = "UPDATE shift_schedules SET start_time = ?, end_time = ?, shift_type = ?, status = ?, notes = ? 
                         WHERE id = ? AND branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$start_time, $end_time, $shift_type, $status, $notes, $schedule_id, $branch_id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Schedule not found or not authorized to update');
                }
                
                echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating schedule: ' . $e->getMessage()]);
            }
            exit();
            
        case 'delete_schedule':
            $schedule_id = (int)$_POST['schedule_id'];
            
            try {
                $query = "DELETE FROM shift_schedules WHERE id = ? AND branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$schedule_id, $branch_id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Schedule not found or not authorized to delete');
                }
                
                echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting schedule: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_schedules':
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
            
            try {
                // Build query based on user role
                if ($_SESSION['user_role'] === 'super_admin') {
                    $query = "SELECT ss.*, 
                             CASE 
                                 WHEN ss.is_worker = 1 THEN w.name 
                                 ELSE u.name 
                             END as user_name,
                             u2.name as created_by_name, b.name as branch_name,
                             sa.actual_start_time, sa.actual_end_time, sa.total_hours, sa.status as attendance_status
                             FROM shift_schedules ss
                             LEFT JOIN users u ON (ss.user_id = u.id AND ss.is_worker = 0)
                             LEFT JOIN workers w ON (ss.user_id = w.id AND ss.is_worker = 1)
                             LEFT JOIN users u2 ON ss.created_by = u2.id
                             LEFT JOIN branches b ON ss.branch_id = b.id
                             LEFT JOIN shift_attendance sa ON ss.id = sa.shift_schedule_id
                             WHERE ss.shift_date BETWEEN ? AND ?
                             ORDER BY ss.shift_date, ss.start_time";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$start_date, $end_date]);
                } else {
                    $query = "SELECT ss.*, 
                             CASE 
                                 WHEN ss.is_worker = 1 THEN w.name 
                                 ELSE u.name 
                             END as user_name,
                             u2.name as created_by_name,
                             sa.actual_start_time, sa.actual_end_time, sa.total_hours, sa.status as attendance_status
                             FROM shift_schedules ss
                             LEFT JOIN users u ON (ss.user_id = u.id AND ss.is_worker = 0)
                             LEFT JOIN workers w ON (ss.user_id = w.id AND ss.is_worker = 1)
                             LEFT JOIN users u2 ON ss.created_by = u2.id
                             LEFT JOIN shift_attendance sa ON ss.id = sa.shift_schedule_id
                             WHERE ss.branch_id = ? AND ss.shift_date BETWEEN ? AND ?
                             ORDER BY ss.shift_date, ss.start_time";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$branch_id, $start_date, $end_date]);
                }
                
                $schedules = $stmt->fetchAll();
                echo json_encode(['success' => true, 'schedules' => $schedules]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading schedules: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_branch_users':
            try {
                $allPeople = [];
                
                // Get branch staff (admin, cashier, staff) - exclude super_admin users
                $query = "SELECT id, name, role, 'user' as type FROM users 
                         WHERE branch_id = ? AND is_active = 1 AND role != 'super_admin' 
                         ORDER BY name";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id]);
                $users = $stmt->fetchAll();
                
                // Add users to the list
                foreach ($users as $user) {
                    $allPeople[] = [
                        'id' => $user['id'],
                        'name' => $user['name'] . ' (' . ucfirst($user['role']) . ')',
                        'type' => 'user',
                        'original_name' => $user['name']
                    ];
                }
                
                // Get workers from current branch
                $query = "SELECT id, worker_id, name, position FROM workers 
                         WHERE branch_id = ? AND is_active = 1 
                         ORDER BY name";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id]);
                $workers = $stmt->fetchAll();
                
                // Add workers to the list
                foreach ($workers as $worker) {
                    $allPeople[] = [
                        'id' => $worker['id'],
                        'name' => $worker['name'] . ' (' . $worker['worker_id'] . ' - ' . $worker['position'] . ')',
                        'type' => 'worker',
                        'worker_id' => $worker['worker_id'],
                        'original_name' => $worker['name']
                    ];
                }
                
                // Sort by name
                usort($allPeople, function($a, $b) {
                    return strcmp($a['original_name'], $b['original_name']);
                });
                
                echo json_encode(['success' => true, 'users' => $allPeople]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading users: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_branch_workers':
            try {
                // Get workers from current branch
                $query = "SELECT id, worker_id, name, position, fingerprint_id FROM workers 
                         WHERE branch_id = ? AND is_active = 1 
                         ORDER BY name";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id]);
                $workers = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'workers' => $workers]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading workers: ' . $e->getMessage()]);
            }
            exit();
            
        case 'add_worker':
            $worker_id = sanitize($_POST['worker_id'] ?? '');
            $name = sanitize($_POST['name'] ?? '');
            $position = sanitize($_POST['position'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $fingerprint_id = sanitize($_POST['fingerprint_id'] ?? '');
            
            try {
                $db->beginTransaction();
                
                // Check if worker_id already exists
                $query = "SELECT id FROM workers WHERE worker_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$worker_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Worker ID already exists');
                }
                
                // Add worker
                $query = "INSERT INTO workers (branch_id, worker_id, name, position, phone, fingerprint_id) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $worker_id, $name, $position, $phone, $fingerprint_id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Worker added successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error adding worker: ' . $e->getMessage()]);
            }
            exit();
            
        case 'import_excel':
            if (!isset($_FILES['excel_file'])) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit();
            }
            
            $file = $_FILES['excel_file'];
            $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
            
            if ($fileType !== 'xlsx' && $fileType !== 'xls' && $fileType !== 'csv') {
                echo json_encode(['success' => false, 'message' => 'Please upload a valid Excel or CSV file (.xlsx, .xls, or .csv)']);
                exit();
            }
            
            try {
                $rows = [];
                
                if ($fileType === 'csv') {
                    // Handle CSV file
                    $handle = fopen($file['tmp_name'], 'r');
                    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                } else {
                    // Handle Excel file
                    require_once '../vendor/autoload.php';
                    
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                }
                
                // Skip header row
                array_shift($rows);
                
                $db->beginTransaction();
                $imported = 0;
                $errors = [];
                
                foreach ($rows as $index => $row) {
                    $rowNum = $index + 2; // +2 because we skipped header and array is 0-indexed
                    
                    // Validate required fields
                    if (empty($row[0]) || empty($row[1]) || empty($row[2]) || empty($row[3])) {
                        $errors[] = "Row $rowNum: Missing required data (Employee ID, Date, Start Time, End Time)";
                        continue;
                    }
                    
                    $employee_id = trim($row[0]);
                    $shift_date = trim($row[1]);
                    $start_time = trim($row[2]);
                    $end_time = trim($row[3]);
                    $shift_type = trim($row[4] ?? 'full_day');
                    $notes = trim($row[5] ?? '');
                    $fingerprint_id = trim($row[6] ?? ''); // For fingerprint device integration
                    
                    // Validate date format
                    if (!DateTime::createFromFormat('Y-m-d', $shift_date)) {
                        $errors[] = "Row $rowNum: Invalid date format. Use YYYY-MM-DD";
                        continue;
                    }
                    
                    // Validate time format
                    if (!DateTime::createFromFormat('H:i:s', $start_time) && !DateTime::createFromFormat('H:i', $start_time)) {
                        $errors[] = "Row $rowNum: Invalid start time format. Use HH:MM or HH:MM:SS";
                        continue;
                    }
                    
                    if (!DateTime::createFromFormat('H:i:s', $end_time) && !DateTime::createFromFormat('H:i', $end_time)) {
                        $errors[] = "Row $rowNum: Invalid end time format. Use HH:MM or HH:MM:SS";
                        continue;
                    }
                    
                    // Check if user or worker exists in this branch
                    $isWorker = false;
                    $actualEmployeeId = null;
                    
                    // First check if it's a user
                    $userQuery = "SELECT id FROM users WHERE id = ? AND branch_id = ? AND is_active = 1";
                    $userStmt = $db->prepare($userQuery);
                    $userStmt->execute([$employee_id, $branch_id]);
                    $userResult = $userStmt->fetch();
                    
                    if ($userResult) {
                        $actualEmployeeId = $userResult['id'];
                        $isWorker = false;
                    } else {
                        // Check if it's a worker
                        $workerQuery = "SELECT id FROM workers WHERE worker_id = ? AND branch_id = ? AND is_active = 1";
                        $workerStmt = $db->prepare($workerQuery);
                        $workerStmt->execute([$employee_id, $branch_id]);
                        $workerResult = $workerStmt->fetch();
                        
                        if ($workerResult) {
                            $actualEmployeeId = $workerResult['id'];
                            $isWorker = true;
                        }
                    }
                    
                    if (!$actualEmployeeId) {
                        $errors[] = "Row $rowNum: Employee/Worker ID $employee_id not found in this branch";
                        continue;
                    }
                    
                    // Check if schedule already exists
                    $existingQuery = "SELECT id FROM shift_schedules WHERE user_id = ? AND shift_date = ? AND start_time = ? AND is_worker = ?";
                    $existingStmt = $db->prepare($existingQuery);
                    $existingStmt->execute([$actualEmployeeId, $shift_date, $start_time, $isWorker ? 1 : 0]);
                    
                    if ($existingStmt->fetch()) {
                        $errors[] = "Row $rowNum: Schedule already exists for this " . ($isWorker ? 'worker' : 'employee') . ", date, and time";
                        continue;
                    }
                    
                    // Insert schedule
                    $insertQuery = "INSERT INTO shift_schedules (branch_id, user_id, shift_date, start_time, end_time, shift_type, notes, fingerprint_id, is_worker, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $db->prepare($insertQuery);
                    $insertStmt->execute([$branch_id, $actualEmployeeId, $shift_date, $start_time, $end_time, $shift_type, $notes, $fingerprint_id, $isWorker ? 1 : 0, $_SESSION['user_id']]);
                    
                    $imported++;
                }
                
                $db->commit();
                
                $message = "Successfully imported $imported schedules";
                if (!empty($errors)) {
                    $message .= ". " . count($errors) . " errors occurred: " . implode(', ', array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $message .= "... and " . (count($errors) - 5) . " more";
                    }
                }
                
                echo json_encode(['success' => true, 'message' => $message, 'imported' => $imported, 'errors' => $errors]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error importing file: ' . $e->getMessage()]);
            }
            exit();
            
        case 'mark_attendance':
            $schedule_id = (int)$_POST['schedule_id'];
            $action = $_POST['action_type']; // start, end, break_start, break_end
            
            try {
                $db->beginTransaction();
                
                // Check if attendance record exists
                $query = "SELECT id, actual_start_time, actual_end_time, break_start, break_end FROM shift_attendance WHERE shift_schedule_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$schedule_id]);
                $attendance = $stmt->fetch();
                
                $current_time = date('Y-m-d H:i:s');
                
                if (!$attendance) {
                    // Create new attendance record
                    if ($action === 'start') {
                        $query = "INSERT INTO shift_attendance (shift_schedule_id, user_id, actual_start_time, status) 
                                 SELECT ?, user_id, ?, 'present' FROM shift_schedules WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$schedule_id, $current_time, $schedule_id]);
                    }
                } else {
                    // Update existing attendance record
                    switch ($action) {
                        case 'start':
                            if (!$attendance['actual_start_time']) {
                                $query = "UPDATE shift_attendance SET actual_start_time = ?, status = 'present' WHERE shift_schedule_id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$current_time, $schedule_id]);
                            }
                            break;
                        case 'end':
                            if (!$attendance['actual_end_time']) {
                                $query = "UPDATE shift_attendance SET actual_end_time = ? WHERE shift_schedule_id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$current_time, $schedule_id]);
                                
                                // Calculate total hours
                                if ($attendance['actual_start_time']) {
                                    $start = new DateTime($attendance['actual_start_time']);
                                    $end = new DateTime($current_time);
                                    $diff = $start->diff($end);
                                    $total_hours = $diff->h + ($diff->i / 60);
                                    
                                    $query = "UPDATE shift_attendance SET total_hours = ? WHERE shift_schedule_id = ?";
                                    $stmt = $db->prepare($query);
                                    $stmt->execute([$total_hours, $schedule_id]);
                                }
                            }
                            break;
                        case 'break_start':
                            if (!$attendance['break_start']) {
                                $query = "UPDATE shift_attendance SET break_start = ? WHERE shift_schedule_id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$current_time, $schedule_id]);
                            }
                            break;
                        case 'break_end':
                            if (!$attendance['break_start'] && !$attendance['break_end']) {
                                $query = "UPDATE shift_attendance SET break_end = ? WHERE shift_schedule_id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$current_time, $schedule_id]);
                            }
                            break;
                    }
                }
                
                // Update schedule status
                if ($action === 'start') {
                    $query = "UPDATE shift_schedules SET status = 'started' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$schedule_id]);
                } elseif ($action === 'end') {
                    $query = "UPDATE shift_schedules SET status = 'completed' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$schedule_id]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error marking attendance: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$stats = [];

// Build WHERE clause based on user role
$whereClause = $user_role === 'super_admin' ? '' : 'WHERE branch_id = ?';
$params = $user_role === 'super_admin' ? [] : [$branch_id];

// Today's schedules
$query = "SELECT COUNT(*) as total FROM shift_schedules " . $whereClause . " AND shift_date = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute($params);
$stats['today_schedules'] = $stmt->fetch()['total'] ?? 0;

// Active shifts
$query = "SELECT COUNT(*) as active FROM shift_schedules " . $whereClause . " AND shift_date = CURDATE() AND status = 'started'";
$stmt = $db->prepare($query);
$stmt->execute($params);
$stats['active_shifts'] = $stmt->fetch()['active'] ?? 0;

// This week's schedules
$query = "SELECT COUNT(*) as weekly FROM shift_schedules " . $whereClause . " AND shift_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$stmt = $db->prepare($query);
$stmt->execute($params);
$stats['weekly_schedules'] = $stmt->fetch()['weekly'] ?? 0;

// Get branch name(s)
if ($user_role === 'super_admin') {
    $branch_name = 'All Branches (Super Admin)';
} else {
    $query = "SELECT name FROM branches WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$branch_id]);
    $branch_name = $stmt->fetch()['name'] ?? 'Unknown Branch';
}

include 'includes/header.php';
?>
    <style>
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .page-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.8em;
        }
        
        .page-header p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .header-actions .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .header-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary { background: linear-gradient(135deg, #20bf55 0%, #01baef 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #212529; }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); color: white; }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .form-actions .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .form-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 95%;
            max-width: 600px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .schedule-table th,
        .schedule-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .schedule-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .schedule-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-scheduled { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-started { background: #d1fae5; color: #065f46; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .attendance-controls {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .attendance-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
        
        .btn-start { background: #28a745; color: white; }
        .btn-end { background: #dc3545; color: white; }
        .btn-break { background: #ffc107; color: #212529; }
        
        .date-filter {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .date-filter form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-filter input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .notification-success { background: #10b981; }
        .notification-error { background: #ef4444; }
        .notification-warning { background: #f59e0b; }
        .notification-info { background: #3b82f6; }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .header-actions {
                justify-content: stretch;
            }
            
            .header-actions .btn {
                flex: 1;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <!-- Page Header -->
    <div class="admin-section">
        <div class="page-header">
            <div>
                <h2>📅 Shift Schedule Management</h2>
                <p>Staff scheduling and attendance tracking for <?php echo htmlspecialchars($branch_name); ?></p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;"><?php echo $stats['today_schedules']; ?></div>
                <div class="stat-label">Today's Schedules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $stats['active_shifts']; ?></div>
                <div class="stat-label">Active Shifts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #17a2b8;"><?php echo $stats['weekly_schedules']; ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #6c757d;">0</div>
                <div class="stat-label">Templates</div>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="date-filter">
            <form id="date-filter-form">
                <label>Date Range:</label>
                <input type="date" id="start-date" value="<?php echo date('Y-m-d'); ?>">
                <span>to</span>
                <input type="date" id="end-date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                <button type="submit" class="btn btn-primary">Load Schedules</button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-section">
        <div class="section-header">
            <h2><i class="fas fa-calendar-week"></i> Shift Schedules</h2>
            <div class="header-actions">
                <button class="btn btn-info" onclick="downloadTemplate()">
                    <i class="fas fa-download"></i> Download Template
                </button>
                <button class="btn btn-warning" onclick="showImportModal()">
                    <i class="fas fa-file-excel"></i> Import Excel
                </button>
                <button class="btn btn-primary" onclick="showManageWorkersModal()">
                    <i class="fas fa-users"></i> Manage Workers
                </button>
                <button class="btn btn-success" onclick="showCreateScheduleModal()">
                    <i class="fas fa-plus"></i> Create Schedule
                </button>
            </div>
        </div>

        <div id="schedules-container">
            <p style="text-align: center; color: #666; padding: 40px;">
                Loading schedules...
            </p>
        </div>
    </div>

    <!-- Manage Workers Modal -->
    <div id="manage-workers-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('manage-workers-modal')">&times;</span>
            <h3><i class="fas fa-users"></i> Manage Workers</h3>
            
            <div style="margin-bottom: 20px;">
                <button class="btn btn-success" onclick="showAddWorkerModal()">
                    <i class="fas fa-plus"></i> Add New Worker
                </button>
            </div>
            
            <div id="workers-container">
                <p style="text-align: center; color: #666; padding: 20px;">
                    Loading workers...
                </p>
            </div>
        </div>
    </div>

    <!-- Add Worker Modal -->
    <div id="add-worker-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('add-worker-modal')">&times;</span>
            <h3><i class="fas fa-user-plus"></i> Add New Worker</h3>
            
            <form id="add-worker-form">
                <div class="form-group">
                    <label>Worker ID</label>
                    <input type="text" id="worker-id" name="worker_id" required placeholder="e.g., W001">
                    <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Unique identifier for the worker
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="worker-name" name="name" required placeholder="Enter worker's full name">
                </div>
                
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" id="worker-position" name="position" required placeholder="e.g., Kitchen Helper, Cleaner">
                </div>
                
                <div class="form-group">
                    <label>Phone Number (Optional)</label>
                    <input type="tel" id="worker-phone" name="phone" placeholder="+92-300-1234567">
                </div>
                
                <div class="form-group">
                    <label>Fingerprint ID (Optional)</label>
                    <input type="text" id="worker-fingerprint" name="fingerprint_id" placeholder="e.g., FP001">
                    <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> For biometric device integration
                    </small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-worker-modal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Worker</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Excel Modal -->
    <div id="import-excel-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('import-excel-modal')">&times;</span>
            <h3><i class="fas fa-file-excel"></i> Import Shift Schedules from Excel</h3>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #495057;">📋 Instructions:</h4>
                <ul style="margin: 0; padding-left: 20px; color: #6c757d;">
                    <li>Download the Excel template first to see the required format</li>
                    <li>Fill in the employee/worker schedules following the template</li>
                    <li>Employee ID must match existing employees or workers in your branch</li>
                    <li>Use YYYY-MM-DD format for dates (e.g., 2024-01-15)</li>
                    <li>Use HH:MM format for times (e.g., 09:00, 17:30)</li>
                    <li>Fingerprint ID is optional for biometric device integration</li>
                    <li>Workers don't need POS login access - they only track attendance</li>
                </ul>
            </div>
            
            <form id="import-excel-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="excel-file">Select Excel or CSV File (.xlsx, .xls, or .csv)</label>
                    <input type="file" id="excel-file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                    <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Maximum file size: 10MB
                    </small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('import-excel-modal')">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="downloadTemplate()">
                        <i class="fas fa-download"></i> Download Template
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-upload"></i> Import Schedules
                    </button>
                </div>
            </form>
            
            <div id="import-progress" style="display: none; margin-top: 20px;">
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                    <i class="fas fa-spinner fa-spin"></i> Importing schedules, please wait...
                </div>
            </div>
        </div>
    </div>

    <!-- Create Schedule Modal -->
    <div id="create-schedule-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('create-schedule-modal')">&times;</span>
            <h3>Create Shift Schedule</h3>
            
            <form id="create-schedule-form">
                <div class="form-group">
                    <label>Staff Member or Worker</label>
                    <select id="schedule-user" required>
                        <option value="">Select staff member or worker...</option>
                    </select>
                    <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Shows branch staff members and workers (excludes super admin users)
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Shift Date</label>
                    <input type="date" id="schedule-date" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" id="schedule-start" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" id="schedule-end" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Shift Type</label>
                    <select id="schedule-type" required>
                        <option value="full_day">Full Day</option>
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                        <option value="evening">Evening</option>
                        <option value="night">Night</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="schedule-notes" rows="3" placeholder="Any additional notes..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-schedule-modal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let branchUsers = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadBranchUsers();
            loadSchedules();
            
            // Set default date to today
            document.getElementById('schedule-date').value = new Date().toISOString().split('T')[0];
        });

        // Load branch users and workers
        function loadBranchUsers() {
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_branch_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    branchUsers = data.users;
                    const select = document.getElementById('schedule-user');
                    select.innerHTML = '<option value="">Select staff member or worker...</option>';
                    
                    // Group by type
                    const users = data.users.filter(person => person.type === 'user');
                    const workers = data.users.filter(person => person.type === 'worker');
                    
                    // Add users section
                    if (users.length > 0) {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = 'Staff Members';
                        users.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.name;
                            option.dataset.type = 'user';
                            optgroup.appendChild(option);
                        });
                        select.appendChild(optgroup);
                    }
                    
                    // Add workers section
                    if (workers.length > 0) {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = 'Workers';
                        workers.forEach(worker => {
                            const option = document.createElement('option');
                            option.value = worker.id;
                            option.textContent = worker.name;
                            option.dataset.type = 'worker';
                            optgroup.appendChild(option);
                        });
                        select.appendChild(optgroup);
                    }
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
            });
        }

        // Load schedules
        function loadSchedules() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_schedules&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySchedules(data.schedules);
                } else {
                    document.getElementById('schedules-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading schedules: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading schedules:', error);
                document.getElementById('schedules-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading schedules. Please try again.</div>';
            });
        }

        // Display schedules
        function displaySchedules(schedules) {
            const container = document.getElementById('schedules-container');
            
            if (schedules.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No schedules found for the selected period</p>';
                return;
            }

            let html = `
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Staff</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Shift Type</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            schedules.forEach(schedule => {
                const statusClass = `status-${schedule.status}`;
                const statusText = schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1);
                
                // Attendance controls
                let attendanceControls = '';
                if (schedule.status === 'scheduled' || schedule.status === 'confirmed') {
                    attendanceControls = `<button class="attendance-btn btn-start" onclick="markAttendance(${schedule.id}, 'start')">Start</button>`;
                } else if (schedule.status === 'started') {
                    attendanceControls = `
                        <button class="attendance-btn btn-end" onclick="markAttendance(${schedule.id}, 'end')">End</button>
                        <button class="attendance-btn btn-break" onclick="markAttendance(${schedule.id}, 'break_start')">Break</button>
                    `;
                }
                
                // Show branch name for super admin
                const staffInfo = schedule.branch_name ? 
                    `${schedule.user_name} (${schedule.branch_name})` : 
                    schedule.user_name;
                
                html += `
                    <tr>
                        <td>${new Date(schedule.shift_date).toLocaleDateString()}</td>
                        <td>${staffInfo}</td>
                        <td>${schedule.start_time}</td>
                        <td>${schedule.end_time}</td>
                        <td>${schedule.shift_type.replace('_', ' ').toUpperCase()}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <div class="attendance-controls">
                                ${attendanceControls}
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-info" onclick="editSchedule(${schedule.id})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger" onclick="deleteSchedule(${schedule.id})">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Show create schedule modal
        function showCreateScheduleModal() {
            document.getElementById('create-schedule-modal').style.display = 'block';
        }
        
        // Show import excel modal
        function showImportModal() {
            document.getElementById('import-excel-modal').style.display = 'block';
        }
        
        // Download template
        function downloadTemplate() {
            window.open('shift_schedule.php?action=download_template', '_blank');
        }
        
        // Show manage workers modal
        function showManageWorkersModal() {
            document.getElementById('manage-workers-modal').style.display = 'block';
            loadWorkers();
        }
        
        // Show add worker modal
        function showAddWorkerModal() {
            document.getElementById('add-worker-modal').style.display = 'block';
        }
        
        // Load workers
        function loadWorkers() {
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_branch_workers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayWorkers(data.workers);
                } else {
                    document.getElementById('workers-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 20px;">Error loading workers: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading workers:', error);
                document.getElementById('workers-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 20px;">Error loading workers. Please try again.</div>';
            });
        }
        
        // Display workers
        function displayWorkers(workers) {
            const container = document.getElementById('workers-container');
            
            if (workers.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No workers found</p>';
                return;
            }

            let html = `
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Worker ID</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Phone</th>
                            <th>Fingerprint ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            workers.forEach(worker => {
                html += `
                    <tr>
                        <td><strong>${worker.worker_id}</strong></td>
                        <td>${worker.name}</td>
                        <td>${worker.position}</td>
                        <td>${worker.phone || 'N/A'}</td>
                        <td>${worker.fingerprint_id || 'N/A'}</td>
                        <td><span class="status-badge status-scheduled">Active</span></td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Create schedule
        document.getElementById('create-schedule-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('schedule-user').value;
            const shiftDate = document.getElementById('schedule-date').value;
            const startTime = document.getElementById('schedule-start').value;
            const endTime = document.getElementById('schedule-end').value;
            const shiftType = document.getElementById('schedule-type').value;
            const notes = document.getElementById('schedule-notes').value;
            
            // Determine if selected person is a worker
            const selectedOption = document.getElementById('schedule-user').selectedOptions[0];
            const isWorker = selectedOption && selectedOption.dataset.type === 'worker' ? 1 : 0;
            
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_schedule&user_id=${userId}&shift_date=${shiftDate}&start_time=${startTime}&end_time=${endTime}&shift_type=${shiftType}&notes=${encodeURIComponent(notes)}&is_worker=${isWorker}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Schedule created successfully', 'success');
                    closeModal('create-schedule-modal');
                    document.getElementById('create-schedule-form').reset();
                    document.getElementById('schedule-date').value = new Date().toISOString().split('T')[0];
                    loadSchedules();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error creating schedule:', error);
                showNotification('Error creating schedule', 'error');
            });
        });

        // Mark attendance
        function markAttendance(scheduleId, action) {
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_attendance&schedule_id=${scheduleId}&action_type=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Attendance marked successfully', 'success');
                    loadSchedules();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error marking attendance:', error);
                showNotification('Error marking attendance', 'error');
            });
        }

        // Edit schedule
        function editSchedule(scheduleId) {
            showNotification('Edit schedule functionality - Coming soon!', 'info');
        }

        // Delete schedule
        function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                fetch('shift_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_schedule&schedule_id=${scheduleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Schedule deleted successfully', 'success');
                        loadSchedules();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting schedule:', error);
                    showNotification('Error deleting schedule', 'error');
                });
            }
        }

        // Excel import form
        document.getElementById('import-excel-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('excel-file');
            const file = fileInput.files[0];
            
            if (!file) {
                showNotification('Please select an Excel file', 'error');
                return;
            }
            
            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                showNotification('File size must be less than 10MB', 'error');
                return;
            }
            
            // Validate file type
            const fileType = file.name.split('.').pop().toLowerCase();
            if (fileType !== 'xlsx' && fileType !== 'xls' && fileType !== 'csv') {
                showNotification('Please select a valid Excel or CSV file (.xlsx, .xls, or .csv)', 'error');
                return;
            }
            
            // Show progress
            document.getElementById('import-progress').style.display = 'block';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'import_excel');
            formData.append('excel_file', file);
            
            // Submit form
            fetch('shift_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('import-progress').style.display = 'none';
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('import-excel-modal');
                    document.getElementById('import-excel-form').reset();
                    loadSchedules();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('import-progress').style.display = 'none';
                console.error('Error importing Excel:', error);
                showNotification('Error importing Excel file', 'error');
            });
        });
        
        // Add worker form
        document.getElementById('add-worker-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const workerId = document.getElementById('worker-id').value;
            const name = document.getElementById('worker-name').value;
            const position = document.getElementById('worker-position').value;
            const phone = document.getElementById('worker-phone').value;
            const fingerprintId = document.getElementById('worker-fingerprint').value;
            
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_worker&worker_id=${encodeURIComponent(workerId)}&name=${encodeURIComponent(name)}&position=${encodeURIComponent(position)}&phone=${encodeURIComponent(phone)}&fingerprint_id=${encodeURIComponent(fingerprintId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Worker added successfully', 'success');
                    closeModal('add-worker-modal');
                    document.getElementById('add-worker-form').reset();
                    loadWorkers(); // Refresh workers list
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error adding worker:', error);
                showNotification('Error adding worker', 'error');
            });
        });
        
        // Date filter form
        document.getElementById('date-filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            loadSchedules();
        });

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
                <button onclick="this.parentElement.remove()" style="
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    cursor: pointer;
                    margin-left: auto;
                    padding: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">&times;</button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }
    </script>

<?php include 'includes/footer.php'; ?>
