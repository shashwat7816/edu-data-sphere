
<?php
// Start session
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "staff") {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
require_once('../database/db_config.php');

// Get staff information
$staff_id = $_SESSION["user_id"];
$sql = "SELECT * FROM university_staff WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $staff = $result->fetch_assoc();
} else {
    // Staff not found, redirect to login
    header("Location: ../auth/login.php");
    exit;
}

// Get university information
$university_id = $staff["university_id"];
$uni_sql = "SELECT name FROM universities WHERE id = ?";
$uni_stmt = $conn->prepare($uni_sql);
$uni_stmt->bind_param("i", $university_id);
$uni_stmt->execute();
$uni_result = $uni_stmt->get_result();
$university = $uni_result->fetch_assoc();

// Handle profile update
$update_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])) {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $department = trim($_POST["department"]);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || empty($department)) {
        $update_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">All fields are required.</div>';
    } else {
        // Update staff profile
        $update_sql = "UPDATE university_staff SET name = ?, email = ?, phone = ?, department = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssi", $name, $email, $phone, $department, $staff_id);
        
        if ($update_stmt->execute()) {
            $update_message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">Profile updated successfully!</div>';
            // Update session data
            $_SESSION["user_name"] = $name;
            $_SESSION["user_email"] = $email;
            
            // Refresh staff data
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
        } else {
            $update_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Error updating profile: ' . $conn->error . '</div>';
        }
    }
}

// Handle password change
$password_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_password"])) {
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">All password fields are required.</div>';
    } elseif ($new_password !== $confirm_password) {
        $password_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">New passwords do not match.</div>';
    } elseif (strlen($new_password) < 8) {
        $password_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">New password must be at least 8 characters long.</div>';
    } else {
        // Verify current password
        if (password_verify($current_password, $staff["password"])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $pass_sql = "UPDATE university_staff SET password = ? WHERE id = ?";
            $pass_stmt = $conn->prepare($pass_sql);
            $pass_stmt->bind_param("si", $hashed_password, $staff_id);
            
            if ($pass_stmt->execute()) {
                $password_message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">Password updated successfully!</div>';
            } else {
                $password_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Error updating password: ' . $conn->error . '</div>';
            }
        } else {
            $password_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Current password is incorrect.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen">
    <!-- Header Navigation -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="../dashboard/staff.php" class="text-2xl font-bold text-blue-600">EduDataSphere</a>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
                    <a href="../auth/logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-6">Staff Profile</h1>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center text-white text-2xl font-bold mr-6">
                            <?php echo strtoupper(substr($staff["name"], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($staff["name"]); ?></h2>
                            <p class="text-gray-600"><?php echo htmlspecialchars($staff["email"]); ?></p>
                            <p class="text-gray-600"><?php echo htmlspecialchars($staff["department"]); ?> Department</p>
                            <p class="text-gray-600"><?php echo htmlspecialchars($university["name"]); ?></p>
                        </div>
                    </div>
                    
                    <!-- Profile Update Form -->
                    <div class="mb-8">
                        <h3 class="text-xl font-semibold mb-4">Update Profile Information</h3>
                        <?php echo $update_message; ?>
                        
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="grid md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($staff["name"]); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($staff["email"]); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($staff["phone"] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label for="department" class="block text-gray-700 text-sm font-bold mb-2">Department</label>
                                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($staff["department"] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                            </div>
                            
                            <div>
                                <button type="submit" name="update_profile" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Password Change Form -->
                    <div>
                        <h3 class="text-xl font-semibold mb-4">Change Password</h3>
                        <?php echo $password_message; ?>
                        
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-4">
                                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                            </div>
                            
                            <div>
                                <button type="submit" name="change_password" class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <a href="../dashboard/staff.php" class="text-blue-600 hover:text-blue-800">
                    &larr; Back to Dashboard
                </a>
            </div>
        </div>
    </main>
    
    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4">
            <p class="text-center text-gray-600">© 2025 EduDataSphere. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
