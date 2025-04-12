
<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
require_once('../database/db_config.php');

// Get universities for dropdown
$universities_sql = "SELECT id, name FROM universities ORDER BY name";
$universities_result = $conn->query($universities_sql);
$universities = [];
while ($row = $universities_result->fetch_assoc()) {
    $universities[] = $row;
}

// Process form submission
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $amount = trim($_POST["amount"]);
    $university_id = $_POST["university_id"] ?? null;
    $eligibility = trim($_POST["eligibility"]);
    $deadline = $_POST["deadline"];
    
    // Validate inputs
    if (empty($name) || empty($description) || empty($amount)) {
        $error = "Name, description, and amount are required.";
    } else {
        // Check if scholarships table exists, if not create it
        $check_table_sql = "SHOW TABLES LIKE 'scholarships'";
        $table_exists = $conn->query($check_table_sql)->num_rows > 0;
        
        if (!$table_exists) {
            $create_table_sql = "CREATE TABLE scholarships (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                university_id INT,
                eligibility TEXT,
                deadline DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL
            )";
            
            if (!$conn->query($create_table_sql)) {
                $error = "Error creating scholarships table: " . $conn->error;
            }
        }
        
        if (empty($error)) {
            // Insert scholarship into database
            $insert_sql = "INSERT INTO scholarships (name, description, amount, university_id, eligibility, deadline, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssdsss", $name, $description, $amount, $university_id, $eligibility, $deadline);
            
            if ($stmt->execute()) {
                $message = "Scholarship added successfully!";
                // Reset form fields after successful submission
                $name = $description = $amount = $eligibility = '';
                $university_id = '';
                $deadline = '';
            } else {
                $error = "Error adding scholarship: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Scholarship | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen">
    <!-- Header Navigation -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between py-4">
                <div class="flex items-center">
                    <a href="../dashboard/admin.php" class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-800">EduDataSphere</span>
                    </a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="../dashboard/admin.php" class="px-3 py-2 text-gray-600 hover:text-blue-600">Dashboard</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Universities</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Students</a>
                    <a href="#" class="px-3 py-2 text-blue-600 font-medium">Scholarships</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Reports</a>
                    
                    <div class="relative ml-4">
                        <button id="userMenuButton" class="flex items-center focus:outline-none">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white">
                                <?php echo substr($_SESSION["user_name"], 0, 1); ?>
                            </div>
                            <span class="ml-2"><?php echo $_SESSION["user_name"]; ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden">
                            <a href="../profile/admin.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Your Profile</a>
                            <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Settings</a>
                            <div class="border-t border-gray-100"></div>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Sign out</a>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobileMenuButton" class="text-gray-500 focus:outline-none">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobileMenu" class="md:hidden hidden pb-4">
                <a href="../dashboard/admin.php" class="block py-2 text-gray-600 hover:text-blue-600">Dashboard</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Universities</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Students</a>
                <a href="#" class="block py-2 text-blue-600 font-medium">Scholarships</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Reports</a>
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <a href="../profile/admin.php" class="block py-2 text-gray-600 hover:text-blue-600">Your Profile</a>
                    <a href="../auth/logout.php" class="block py-2 text-gray-600 hover:text-blue-600">Sign out</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Add New Scholarship</h1>
                <a href="../dashboard/admin.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                    <p><?php echo $message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Scholarship Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description *</label>
                        <textarea id="description" name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="amount" class="block text-gray-700 text-sm font-bold mb-2">Amount (USD) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?php echo isset($amount) ? htmlspecialchars($amount) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="university_id" class="block text-gray-700 text-sm font-bold mb-2">Associated University</label>
                        <select id="university_id" name="university_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Not Specific to Any University --</option>
                            <?php foreach ($universities as $university): ?>
                                <option value="<?php echo $university['id']; ?>" <?php echo (isset($university_id) && $university_id == $university['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($university['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="eligibility" class="block text-gray-700 text-sm font-bold mb-2">Eligibility Criteria</label>
                        <textarea id="eligibility" name="eligibility" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo isset($eligibility) ? htmlspecialchars($eligibility) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="deadline" class="block text-gray-700 text-sm font-bold mb-2">Application Deadline</label>
                        <input type="date" id="deadline" name="deadline" value="<?php echo isset($deadline) ? htmlspecialchars($deadline) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                            Create Scholarship
                        </button>
                        <a href="../dashboard/admin.php" class="text-gray-600 hover:text-gray-800">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4">
            <div class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> EduDataSphere. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Toggle user menu dropdown
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');
        
        if (userMenuButton && userMenu) {
            userMenuButton.addEventListener('click', function() {
                userMenu.classList.toggle('hidden');
            });
            
            // Close the dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        }
        
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
