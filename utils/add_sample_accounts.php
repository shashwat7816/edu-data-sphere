
<?php
// Database connection
require_once('../database/db_config.php');

// Function to add a sample admin account
function add_sample_admin($conn, $name, $email, $password, $designation) {
    // Check if admin with this email already exists
    $check_sql = "SELECT id FROM admins WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p>Admin with email '{$email}' already exists.</p>";
        return;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new admin
    $sql = "INSERT INTO admins (name, email, password, designation) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $designation);
    
    if ($stmt->execute()) {
        echo "<p class='success'>✅ Admin '{$name}' added successfully!</p>";
    } else {
        echo "<p class='error'>❌ Error adding admin: " . $conn->error . "</p>";
    }
}

// Function to add a sample staff account
function add_sample_staff($conn, $name, $email, $password, $university_id, $designation) {
    // Check if staff with this email already exists
    $check_sql = "SELECT id FROM university_staff WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p>Staff with email '{$email}' already exists.</p>";
        return;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new staff
    $sql = "INSERT INTO university_staff (name, email, password, university_id, designation) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $university_id, $designation);
    
    if ($stmt->execute()) {
        // Get university name
        $uni_sql = "SELECT name FROM universities WHERE id = ?";
        $uni_stmt = $conn->prepare($uni_sql);
        $uni_stmt->bind_param("i", $university_id);
        $uni_stmt->execute();
        $uni_result = $uni_stmt->get_result();
        $uni_name = ($uni_result->num_rows > 0) ? $uni_result->fetch_assoc()['name'] : "Unknown University";
        
        echo "<p class='success'>✅ Staff '{$name}' added successfully to {$uni_name}!</p>";
    } else {
        echo "<p class='error'>❌ Error adding staff: " . $conn->error . "</p>";
    }
}

// Check if form submitted or direct execution
$is_form_submit = isset($_POST['create_accounts']);
$is_direct_execution = !$is_form_submit && isset($_GET['direct']) && $_GET['direct'] == 1;

// Only proceed if form submitted or direct execution requested
if ($is_form_submit || $is_direct_execution) {
    // Default credentials
    $default_admin = [
        'name' => 'System Administrator',
        'email' => 'admin@edudatasphere.com',
        'password' => 'admin123',
        'designation' => 'System Administrator'
    ];

    $default_staff = [
        [
            'name' => 'Delhi University Staff',
            'email' => 'staff_delhi@edudatasphere.com',
            'password' => 'staff123',
            'university_id' => 1,
            'designation' => 'Registrar'
        ],
        [
            'name' => 'Mumbai University Staff',
            'email' => 'staff_mumbai@edudatasphere.com',
            'password' => 'staff123',
            'university_id' => 2,
            'designation' => 'Academic Coordinator'
        ],
        [
            'name' => 'IIT Bombay Staff',
            'email' => 'staff_iit@edudatasphere.com',
            'password' => 'staff123',
            'university_id' => 3,
            'designation' => 'Department Head'
        ]
    ];

    // Use form data if available, otherwise use defaults
    if ($is_form_submit) {
        // Process admin account from form
        if (isset($_POST['admin_name']) && !empty($_POST['admin_name'])) {
            $admin_name = $_POST['admin_name'];
            $admin_email = $_POST['admin_email'];
            $admin_password = $_POST['admin_password'];
            $admin_designation = $_POST['admin_designation'];
            
            add_sample_admin($conn, $admin_name, $admin_email, $admin_password, $admin_designation);
        }
        
        // Process staff account from form
        if (isset($_POST['staff_name']) && !empty($_POST['staff_name'])) {
            $staff_name = $_POST['staff_name'];
            $staff_email = $_POST['staff_email'];
            $staff_password = $_POST['staff_password'];
            $staff_university_id = $_POST['staff_university_id'];
            $staff_designation = $_POST['staff_designation'];
            
            add_sample_staff($conn, $staff_name, $staff_email, $staff_password, $staff_university_id, $staff_designation);
        }
    } else if ($is_direct_execution) {
        // Add default admin
        add_sample_admin(
            $conn, 
            $default_admin['name'], 
            $default_admin['email'], 
            $default_admin['password'], 
            $default_admin['designation']
        );
        
        // Add default staff members
        foreach ($default_staff as $staff) {
            add_sample_staff(
                $conn, 
                $staff['name'], 
                $staff['email'], 
                $staff['password'], 
                $staff['university_id'], 
                $staff['designation']
            );
        }
    }
}

// Get existing universities for dropdown
$universities = [];
$uni_query = "SELECT id, name FROM universities ORDER BY name";
$uni_result = $conn->query($uni_query);
if ($uni_result && $uni_result->num_rows > 0) {
    while ($row = $uni_result->fetch_assoc()) {
        $universities[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sample Accounts | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .success {
            color: #15803d;
            padding: 0.75rem;
            background-color: #dcfce7;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        .error {
            color: #b91c1c;
            padding: 0.75rem;
            background-color: #fee2e2;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Add Sample Accounts</h1>
            <a href="../index.html" class="text-blue-600 hover:text-blue-800">
                &larr; Back to Home
            </a>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Quick Setup</h2>
            <p class="mb-4 text-gray-600">Click the button below to automatically add sample admin and staff accounts with default credentials.</p>
            
            <a href="?direct=1" class="inline-block bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700 transition">
                Create Default Accounts
            </a>
            
            <div class="mt-4 p-4 bg-blue-50 text-blue-800 rounded">
                <h3 class="font-semibold">Default Credentials</h3>
                <p class="mt-2 mb-1"><strong>Admin:</strong> admin@edudatasphere.com / admin123</p>
                <p><strong>Staff:</strong> staff_delhi@edudatasphere.com / staff123 (and others)</p>
            </div>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Custom Account Creation</h2>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <!-- Admin Account Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Admin Account</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" id="admin_name" name="admin_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="admin_email" name="admin_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="text" id="admin_password" name="admin_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="admin_designation" class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                            <input type="text" id="admin_designation" name="admin_designation" value="System Administrator" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <!-- Staff Account Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Staff Account</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="staff_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" id="staff_name" name="staff_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="staff_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="staff_email" name="staff_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="staff_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="text" id="staff_password" name="staff_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="staff_university_id" class="block text-sm font-medium text-gray-700 mb-1">University</label>
                            <select id="staff_university_id" name="staff_university_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select University</option>
                                <?php foreach ($universities as $university): ?>
                                    <option value="<?php echo $university['id']; ?>"><?php echo htmlspecialchars($university['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="staff_designation" class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                            <input type="text" id="staff_designation" name="staff_designation" value="University Staff" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" name="create_accounts" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                        Create Accounts
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-8">
            <a href="../auth/login.php" class="text-blue-600 hover:text-blue-800">
                Go to Login Page &rarr;
            </a>
        </div>
    </div>
</body>
</html>
