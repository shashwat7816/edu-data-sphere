
<?php
// Start session
session_start();

// Check if user is logged in and is a government staff
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "staff") {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
require_once('../database/db_config.php');
require_once('../utils/dashboard_functions.php');

// Process form submission
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $notice_title = trim($_POST["notice_title"]);
    $notice_content = trim($_POST["notice_content"]);
    $notice_type = $_POST["notice_type"];
    $university_id = $_POST["university_id"];
    
    // Basic validation
    if (empty($notice_title) || empty($notice_content) || empty($notice_type)) {
        $error = "All fields are required.";
    } else {
        // Add notice to database
        $result = addUniversityNotice($conn, $university_id, $notice_title, $notice_content, $notice_type);
        
        if ($result === true) {
            $message = "Notice has been published successfully!";
        } else {
            $error = $result;
        }
    }
    
    // Redirect back to staff dashboard
    header("Location: staff.php?notice_added=" . ($message ? "1" : "0") . "&error=" . urlencode($error));
    exit;
}
?>
