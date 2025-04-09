
<?php
// Start session
session_start();

// Database connection
require_once('../database/db_config.php');

// Initialize variables
$name = $email = $aadhaar = $university = $password = $confirm_password = "";
$errors = [];

// Get universities for dropdown
$universities = [];
$uni_query = "SELECT id, name FROM universities ORDER BY name";
$uni_result = $conn->query($uni_query);

if ($uni_result && $uni_result->num_rows > 0) {
    while ($row = $uni_result->fetch_assoc()) {
        $universities[] = $row;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $aadhaar = trim($_POST["aadhaar"]);
    $university = $_POST["university"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    
    // Validate name
    if (empty($name)) {
        $errors["name"] = "Name is required";
    }
    
    // Validate email
    if (empty($email)) {
        $errors["email"] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Invalid email format";
    } else {
        // Check if email exists
        $check_sql = "SELECT id FROM students WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $errors["email"] = "Email already exists";
        }
    }
    
    // Validate Aadhaar number (12 digits)
    if (empty($aadhaar)) {
        $errors["aadhaar"] = "Aadhaar number is required";
    } elseif (!preg_match("/^[0-9]{12}$/", $aadhaar)) {
        $errors["aadhaar"] = "Aadhaar must be exactly 12 digits";
    }
    
    // Validate university
    if (empty($university)) {
        $errors["university"] = "University selection is required";
    }
    
    // Validate password
    if (empty($password)) {
        $errors["password"] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors["password"] = "Password must be at least 8 characters";
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors["confirm_password"] = "Passwords do not match";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare SQL statement
        $sql = "INSERT INTO students (name, email, aadhaar_number, university_id, password, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $email, $aadhaar, $university, $hashed_password);
        
        // Execute the statement
        if ($stmt->execute()) {
            // Registration successful
            $_SESSION["register_success"] = true;
            header("Location: login.php");
            exit;
        } else {
            // Registration failed
            $errors["general"] = "Registration failed: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-blue-600 px-6 py-8 text-white text-center">
                <h1 class="text-3xl font-bold mb-2">Create Your Account</h1>
                <p>Join EduDataSphere as a student</p>
            </div>
            
            <div class="p-6">
                <?php if (!empty($errors["general"])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo $errors["general"]; ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo $name; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php if (!empty($errors["name"])): ?>
                                <p class="text-red-500 text-xs mt-1"><?php echo $errors["name"]; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo $email; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php if (!empty($errors["email"])): ?>
                                <p class="text-red-500 text-xs mt-1"><?php echo $errors["email"]; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="aadhaar" class="block text-gray-700 text-sm font-bold mb-2">Aadhaar Number</label>
                            <input type="text" id="aadhaar" name="aadhaar" value="<?php echo $aadhaar; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required maxlength="12">
                            <?php if (!empty($errors["aadhaar"])): ?>
                                <p class="text-red-500 text-xs mt-1"><?php echo $errors["aadhaar"]; ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500 text-xs mt-1">Enter your 12-digit Aadhaar number</p>
                        </div>
                        
                        <div>
                            <label for="university" class="block text-gray-700 text-sm font-bold mb-2">University</label>
                            <select id="university" name="university" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">Select University</option>
                                <?php foreach ($universities as $uni): ?>
                                    <option value="<?php echo $uni['id']; ?>" <?php if ($university == $uni['id']) echo "selected"; ?>>
                                        <?php echo $uni['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors["university"])): ?>
                                <p class="text-red-500 text-xs mt-1"><?php echo $errors["university"]; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                            <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php if (!empty($errors["password"])): ?>
                                <p class="text-red-500 text-xs mt-1"><?php echo $errors["password"]; ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500 text-xs mt-1">Password must be at least 8 characters</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php if (!empty($errors["confirm_password"])): ?>
                                <p class="text-red-500 text-xs mt-1"><?php echo $errors["confirm_password"]; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="terms" name="terms" class="mr-2" required>
                            <label for="terms" class="text-sm text-gray-700">
                                I agree to the <a href="#" class="text-blue-600 hover:text-blue-800">Terms of Service</a> and <a href="#" class="text-blue-600 hover:text-blue-800">Privacy Policy</a>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                            Create Account
                        </button>
                    </div>
                </form>
                
                <p class="text-center text-gray-600 text-sm">
                    Already have an account? <a href="login.php" class="text-blue-600 hover:text-blue-800">Sign in</a>
                </p>
            </div>
        </div>
        
        <div class="mt-8 text-center">
            <a href="../index.html" class="text-blue-600 hover:text-blue-800">
                &larr; Back to Home
            </a>
        </div>
    </div>

    <script>
        // Client-side validation for Aadhaar
        document.getElementById('aadhaar').addEventListener('input', function(e) {
            // Allow only numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 12 characters
            if (this.value.length > 12) {
                this.value = this.value.slice(0, 12);
            }
        });
    </script>
</body>
</html>
