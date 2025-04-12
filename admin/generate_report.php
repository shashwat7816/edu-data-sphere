
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
require_once('../utils/dashboard_functions.php');

// Handle report generation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["generate_report"])) {
    $report_type = $_POST["report_type"];
    $university_id = !empty($_POST["university_id"]) ? $_POST["university_id"] : null;
    $date_from = !empty($_POST["date_from"]) ? $_POST["date_from"] : null;
    $date_to = !empty($_POST["date_to"]) ? $_POST["date_to"] : null;
    
    // Build query based on report type
    $data = [];
    $filters = [
        'university_id' => $university_id,
        'date_from' => $date_from,
        'date_to' => $date_to
    ];
    
    switch ($report_type) {
        case 'students':
            $sql = "SELECT s.id, s.name, s.email, s.gender, s.date_of_birth, s.created_at, 
                    u.name as university_name, p.program_name 
                    FROM students s 
                    LEFT JOIN universities u ON s.university_id = u.id 
                    LEFT JOIN programs p ON s.program_id = p.id 
                    WHERE 1=1";
            
            if ($university_id) {
                $sql .= " AND s.university_id = '$university_id'";
            }
            
            if ($date_from) {
                $sql .= " AND s.created_at >= '$date_from'";
            }
            
            if ($date_to) {
                $sql .= " AND s.created_at <= '$date_to 23:59:59'";
            }
            
            $result = $conn->query($sql);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            
            if (!empty($data)) {
                $filename = "students_report_" . date('Y-m-d') . ".csv";
                generateAndLogCSV($conn, $data, $filename, $_SESSION["user_id"], 'students', $filters);
            }
            break;
            
        case 'universities':
            $sql = "SELECT u.id, u.name, u.location, u.website, u.established_year, 
                    u.created_at, COUNT(s.id) as student_count 
                    FROM universities u 
                    LEFT JOIN students s ON u.id = s.university_id 
                    WHERE 1=1";
            
            if ($university_id) {
                $sql .= " AND u.id = '$university_id'";
            }
            
            if ($date_from) {
                $sql .= " AND u.created_at >= '$date_from'";
            }
            
            if ($date_to) {
                $sql .= " AND u.created_at <= '$date_to 23:59:59'";
            }
            
            $sql .= " GROUP BY u.id";
            
            $result = $conn->query($sql);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            
            if (!empty($data)) {
                $filename = "universities_report_" . date('Y-m-d') . ".csv";
                generateAndLogCSV($conn, $data, $filename, $_SESSION["user_id"], 'universities', $filters);
            }
            break;
            
        case 'departments':
            $sql = "SELECT d.id, d.name, d.description, d.created_at, 
                    u.name as university_name, COUNT(c.id) as course_count 
                    FROM departments d 
                    LEFT JOIN universities u ON d.university_id = u.id 
                    LEFT JOIN courses c ON d.id = c.department_id 
                    WHERE 1=1";
            
            if ($university_id) {
                $sql .= " AND d.university_id = '$university_id'";
            }
            
            if ($date_from) {
                $sql .= " AND d.created_at >= '$date_from'";
            }
            
            if ($date_to) {
                $sql .= " AND d.created_at <= '$date_to 23:59:59'";
            }
            
            $sql .= " GROUP BY d.id";
            
            $result = $conn->query($sql);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            
            if (!empty($data)) {
                $filename = "departments_report_" . date('Y-m-d') . ".csv";
                generateAndLogCSV($conn, $data, $filename, $_SESSION["user_id"], 'departments', $filters);
            }
            break;
            
        case 'courses':
            $sql = "SELECT c.id, c.course_code, c.title, c.description, c.created_at, 
                    u.name as university_name, d.name as department_name, c.credit_hours 
                    FROM courses c 
                    LEFT JOIN departments d ON c.department_id = d.id 
                    LEFT JOIN universities u ON c.university_id = u.id 
                    WHERE 1=1";
            
            if ($university_id) {
                $sql .= " AND c.university_id = '$university_id'";
            }
            
            if ($date_from) {
                $sql .= " AND c.created_at >= '$date_from'";
            }
            
            if ($date_to) {
                $sql .= " AND c.created_at <= '$date_to 23:59:59'";
            }
            
            $result = $conn->query($sql);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            
            if (!empty($data)) {
                $filename = "courses_report_" . date('Y-m-d') . ".csv";
                generateAndLogCSV($conn, $data, $filename, $_SESSION["user_id"], 'courses', $filters);
            }
            break;
    }
    
    if (empty($data)) {
        $error_message = "No data found for the selected criteria.";
    }
}

// Get universities for filter
$universities_sql = "SELECT id, name FROM universities ORDER BY name";
$universities_result = $conn->query($universities_sql);
$universities = [];
while ($row = $universities_result->fetch_assoc()) {
    $universities[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports | EduDataSphere</title>
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
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Analytics</a>
                    <a href="#" class="px-3 py-2 text-blue-600 font-medium">Reports</a>
                    
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
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Analytics</a>
                <a href="#" class="block py-2 text-blue-600 font-medium">Reports</a>
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
                <h1 class="text-2xl font-bold">Generate Reports</h1>
                <a href="../dashboard/admin.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-gray-700 mb-4">Select the type of report you want to generate and apply any filters as needed.</p>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <label for="report_type" class="block text-gray-700 text-sm font-bold mb-2">Report Type *</label>
                        <select id="report_type" name="report_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select Report Type</option>
                            <option value="students">Students Report</option>
                            <option value="universities">Universities Report</option>
                            <option value="departments">Departments Report</option>
                            <option value="courses">Courses Report</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="university_id" class="block text-gray-700 text-sm font-bold mb-2">Filter by University</label>
                        <select id="university_id" name="university_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Universities</option>
                            <?php foreach ($universities as $university): ?>
                                <option value="<?php echo $university['id']; ?>">
                                    <?php echo htmlspecialchars($university['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="date_from" class="block text-gray-700 text-sm font-bold mb-2">Date From</label>
                            <input type="date" id="date_from" name="date_from" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-gray-700 text-sm font-bold mb-2">Date To</label>
                            <input type="date" id="date_to" name="date_to" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" name="generate_report" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Generate CSV Report
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Reports</h2>
                
                <?php
                // Get recent export logs
                $logs_sql = "SELECT e.*, a.name as admin_name, a.email as admin_email 
                            FROM data_export_logs e 
                            JOIN admins a ON e.user_id = a.id 
                            ORDER BY export_date DESC 
                            LIMIT 5";
                $logs_result = $conn->query($logs_sql);
                
                if ($logs_result && $logs_result->num_rows > 0):
                ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Report Type
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Generated By
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Records
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($log = $logs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo ucfirst($log['export_type']); ?> Report
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $log['admin_name']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $log['admin_email']; ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date("M j, Y, g:i a", strtotime($log['export_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($log['records_exported']); ?> records
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No reports have been generated yet.</p>
                    </div>
                <?php endif; ?>
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
