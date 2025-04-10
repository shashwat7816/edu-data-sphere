
<?php
// Common utility functions for dashboards

/**
 * Get recent notices for a specific university
 * @param int $university_id The university ID
 * @param int $limit Maximum number of notices to retrieve
 * @return array Array of notice records
 */
function getUniversityNotices($conn, $university_id, $limit = 3) {
    $notices = [];
    $notices_sql = "SELECT * FROM notices WHERE university_id = ? ORDER BY created_at DESC LIMIT ?";
    $notices_stmt = $conn->prepare($notices_sql);
    $notices_stmt->bind_param("ii", $university_id, $limit);
    $notices_stmt->execute();
    $notices_result = $notices_stmt->get_result();
    
    while ($row = $notices_result->fetch_assoc()) {
        $notices[] = $row;
    }
    
    return $notices;
}

/**
 * Save a new notice to the database
 * @param object $conn Database connection
 * @param int $university_id The university ID
 * @param string $title Notice title
 * @param string $content Notice content
 * @param string $notice_type Type of notice
 * @return bool|string True on success, error message on failure
 */
function addUniversityNotice($conn, $university_id, $title, $content, $notice_type) {
    $insert_sql = "INSERT INTO notices (university_id, title, content, notice_type, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("isss", $university_id, $title, $content, $notice_type);
    
    if ($insert_stmt->execute()) {
        return true;
    } else {
        return "Error: " . $conn->error;
    }
}

/**
 * Get student deadlines
 * @param object $conn Database connection
 * @param int $student_id The student ID
 * @param int $limit Maximum number of deadlines to retrieve
 * @return array Array of deadline records
 */
function getStudentDeadlines($conn, $student_id, $limit = 5) {
    $deadlines = [];
    $university_id_sql = "SELECT university_id FROM students WHERE id = ?";
    $uni_stmt = $conn->prepare($university_id_sql);
    $uni_stmt->bind_param("i", $student_id);
    $uni_stmt->execute();
    $uni_result = $uni_stmt->get_result();
    $student = $uni_result->fetch_assoc();
    
    if ($student) {
        $university_id = $student['university_id'];
        
        // Get deadlines from notices marked as deadline type
        $deadlines_sql = "SELECT id, title, content, created_at as date 
                         FROM notices 
                         WHERE university_id = ? AND notice_type = 'deadline' 
                         AND DATE(created_at) >= CURDATE()
                         ORDER BY created_at ASC 
                         LIMIT ?";
        $deadlines_stmt = $conn->prepare($deadlines_sql);
        $deadlines_stmt->bind_param("ii", $university_id, $limit);
        $deadlines_stmt->execute();
        $deadlines_result = $deadlines_stmt->get_result();
        
        while ($row = $deadlines_result->fetch_assoc()) {
            $deadlines[] = $row;
        }
    }
    
    return $deadlines;
}

/**
 * Get system health metrics
 * @return array System health metrics
 */
function getSystemHealth() {
    // In a real application, these would be actual metrics
    // For this demo, we'll return simulated values
    return [
        'storage' => 65,
        'cpu' => 25,
        'memory' => 42,
        'status' => 'operational'
    ];
}

/**
 * Log data access by government staff
 * @param object $conn Database connection
 * @param int $user_id User ID of the staff
 * @param string $access_type Type of access (view, export, report)
 * @param string $data_type Type of data being accessed
 * @param int|null $university_id University ID if applicable
 * @return bool True on success, false on failure
 */
function logDataAccess($conn, $user_id, $access_type, $data_type, $university_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $log_sql = "INSERT INTO data_access_logs 
                (user_id, access_type, data_type, university_id, ip_address) 
                VALUES (?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("issss", $user_id, $access_type, $data_type, $university_id, $ip_address);
    
    return $log_stmt->execute();
}

/**
 * Log data exports by government staff
 * @param object $conn Database connection
 * @param int $user_id User ID of the staff
 * @param string $export_type Type of data being exported
 * @param int $record_count Number of records exported
 * @param array $filters Filters applied to export
 * @return bool True on success, false on failure
 */
function logDataExport($conn, $user_id, $export_type, $record_count, $filters = []) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $filters_json = json_encode($filters);
    
    $log_sql = "INSERT INTO data_export_logs 
                (user_id, export_type, records_exported, filters, ip_address) 
                VALUES (?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("isiss", $user_id, $export_type, $record_count, $filters_json, $ip_address);
    
    return $log_stmt->execute();
}

/**
 * Generate CSV export of data and log the export
 * @param object $conn Database connection
 * @param array $data Data to export
 * @param string $filename Filename for the exported CSV
 * @param int $user_id User ID of the exporting staff
 * @param string $export_type Type of data being exported
 * @param array $filters Filters applied to the data
 */
function generateAndLogCSV($conn, $data, $filename, $user_id, $export_type, $filters = []) {
    if (empty($data)) return;
    
    // Log the export
    logDataExport($conn, $user_id, $export_type, count($data), $filters);
    
    // Get column headers from first row
    $headers = array_keys($data[0]);
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Start output buffering
    ob_start();
    
    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    // Close the output stream
    fclose($output);
    exit();
}
?>
