
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
?>
