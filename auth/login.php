
<?php
// Start session
session_start();

// Database connection
require_once('../database/db_config.php');

// Initialize variables
$email = $password = "";
$role = "student"; // Default role
$error = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $role = $_POST["role"];

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        // Based on role, query the appropriate table
        if ($role === "admin") {
            $table = "admins";
        } elseif ($role === "staff") {
            $table = "university_staff";
        } else {
            $table = "students";
        }

        // Prepare SQL statement to prevent SQL injection
        $sql = "SELECT * FROM {$table} WHERE email = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user["password"])) {
                // Password is correct, create session
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_role"] = $role;
                
                // Log login activity
                $log_sql = "INSERT INTO login_logs (user_id, role, login_time, ip_address) VALUES (?, ?, NOW(), ?)";
                $log_stmt = $conn->prepare($log_sql);
                $ip = $_SERVER["REMOTE_ADDR"];
                $log_stmt->bind_param("iss", $user["id"], $role, $ip);
                $log_stmt->execute();
                
                // Redirect based on role
                if ($role === "admin") {
                    header("Location: ../dashboard/admin.php");
                } elseif ($role === "staff") {
                    header("Location: ../dashboard/staff.php");
                } else {
                    header("Location: ../dashboard/student.php");
                }
                exit;
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "No account found with that email address";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-blue-600 px-6 py-8 text-white text-center">
                <h1 class="text-3xl font-bold mb-2">Welcome Back</h1>
                <p>Sign in to access your EduDataSphere account</p>
            </div>
            
            <div class="p-6">
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                        <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <a href="#" class="text-xs text-blue-600 hover:text-blue-800 mt-2 inline-block">Forgot your password?</a>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Login As</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="role" value="student" <?php if ($role === "student") echo "checked"; ?> class="form-radio text-blue-600">
                                <span class="ml-2">Student</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="role" value="staff" <?php if ($role === "staff") echo "checked"; ?> class="form-radio text-blue-600">
                                <span class="ml-2">University Staff</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="role" value="admin" <?php if ($role === "admin") echo "checked"; ?> class="form-radio text-blue-600">
                                <span class="ml-2">Admin</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                            Sign In
                        </button>
                    </div>
                </form>
                
                <p class="text-center text-gray-600 text-sm">
                    Don't have an account? <a href="register.php" class="text-blue-600 hover:text-blue-800">Register now</a>
                </p>
            </div>
        </div>
        
        <div class="mt-8 text-center">
            <a href="../index.html" class="text-blue-600 hover:text-blue-800">
                &larr; Back to Home
            </a>
        </div>
    </div>
</body>
</html>
