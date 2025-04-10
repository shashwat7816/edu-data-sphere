
<?php
// Start session
session_start();

// Include database connection
require_once('../database/db_config.php');

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $role = $_POST["role"];
    
    // Validate input
    if (empty($email) || empty($password) || empty($role)) {
        $_SESSION["login_error"] = "All fields are required.";
        header("Location: login.php");
        exit;
    }
    
    // Determine table to query based on role
    $table = "";
    switch ($role) {
        case "admin":
            $table = "admins";
            break;
        case "staff":
            $table = "university_staff";
            break;
        case "student":
            $table = "students";
            break;
        default:
            $_SESSION["login_error"] = "Invalid role selected.";
            header("Location: login.php");
            exit;
    }
    
    // Prepare SQL statement
    $sql = "SELECT * FROM $table WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user["password"])) {
            // Password is correct, set session variables
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $role;
            
            // Log the login activity
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            
            $log_sql = "INSERT INTO login_logs (user_id, role, ip_address, user_agent) VALUES (?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("isss", $user["id"], $role, $ip_address, $user_agent);
            $log_stmt->execute();
            
            // Redirect based on role
            switch ($role) {
                case "admin":
                    header("Location: ../dashboard/admin.php");
                    break;
                case "staff":
                    header("Location: ../dashboard/staff.php");
                    break;
                case "student":
                    header("Location: ../dashboard/student.php");
                    break;
            }
            exit;
        } else {
            $_SESSION["login_error"] = "Invalid email or password.";
            header("Location: login.php");
            exit;
        }
    } else {
        $_SESSION["login_error"] = "Invalid email or password.";
        header("Location: login.php");
        exit;
    }
} else {
    // If not submitted via POST, redirect to login page
    header("Location: login.php");
    exit;
}
?>
