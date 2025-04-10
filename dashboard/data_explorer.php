
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

// Get staff information
$staff_id = $_SESSION["user_id"];
$staff_sql = "SELECT s.*, u.name as university_name 
              FROM university_staff s 
              JOIN universities u ON s.university_id = u.id 
              WHERE s.id = ?";

$stmt = $conn->prepare($staff_sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff_result = $stmt->get_result();
$staff = $staff_result->fetch_assoc();
$university_id = $staff["university_id"];

// Get all universities if requested
$universities = [];
$universities_sql = "SELECT id, name, location, established_date FROM universities";
$universities_result = $conn->query($universities_sql);
while ($row = $universities_result->fetch_assoc()) {
    $universities[] = $row;
}

// Get data based on filter
$data_type = isset($_GET['data_type']) ? $_GET['data_type'] : 'students';
$filter_uni = isset($_GET['university']) ? intval($_GET['university']) : 0;

// Prepare data arrays
$student_data = [];
$department_data = [];
$course_data = [];
$program_data = [];

// Function to fetch data for charts
function getChartData($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $types = '';
        $values = [];
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_double($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }
        
        $stmt->bind_param($types, ...$values);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Get data based on selected type and filters
switch ($data_type) {
    case 'students':
        // Build query based on filter
        if ($filter_uni > 0) {
            $student_sql = "SELECT s.*, u.name as university_name, p.program_name 
                           FROM students s
                           LEFT JOIN universities u ON s.university_id = u.id
                           LEFT JOIN programs p ON s.program_id = p.id
                           WHERE s.university_id = ?
                           ORDER BY s.name";
            $student_data = getChartData($conn, $student_sql, [$filter_uni]);
        } else {
            $student_sql = "SELECT s.*, u.name as university_name, p.program_name 
                           FROM students s
                           LEFT JOIN universities u ON s.university_id = u.id
                           LEFT JOIN programs p ON s.program_id = p.id
                           ORDER BY u.name, s.name";
            $student_data = getChartData($conn, $student_sql);
        }
        
        // Get chart data - Students per university
        $chart_sql = "SELECT u.name, COUNT(*) as count 
                      FROM students s
                      JOIN universities u ON s.university_id = u.id
                      GROUP BY s.university_id
                      ORDER BY count DESC";
        $chart_data = getChartData($conn, $chart_sql);
        break;
        
    case 'departments':
        // Build query based on filter
        if ($filter_uni > 0) {
            $dept_sql = "SELECT d.*, u.name as university_name 
                        FROM departments d
                        LEFT JOIN universities u ON d.university_id = u.id
                        WHERE d.university_id = ?
                        ORDER BY d.name";
            $department_data = getChartData($conn, $dept_sql, [$filter_uni]);
        } else {
            $dept_sql = "SELECT d.*, u.name as university_name 
                        FROM departments d
                        LEFT JOIN universities u ON d.university_id = u.id
                        ORDER BY u.name, d.name";
            $department_data = getChartData($conn, $dept_sql);
        }
        
        // Get chart data - Departments per university
        $chart_sql = "SELECT u.name, COUNT(*) as count 
                      FROM departments d
                      JOIN universities u ON d.university_id = u.id
                      GROUP BY d.university_id
                      ORDER BY count DESC";
        $chart_data = getChartData($conn, $chart_sql);
        break;
        
    case 'courses':
        // Build query based on filter
        if ($filter_uni > 0) {
            $course_sql = "SELECT c.*, u.name as university_name, d.name as department_name
                          FROM courses c
                          LEFT JOIN universities u ON c.university_id = u.id
                          LEFT JOIN departments d ON c.department_id = d.id
                          WHERE c.university_id = ?
                          ORDER BY c.title";
            $course_data = getChartData($conn, $course_sql, [$filter_uni]);
        } else {
            $course_sql = "SELECT c.*, u.name as university_name, d.name as department_name 
                          FROM courses c
                          LEFT JOIN universities u ON c.university_id = u.id
                          LEFT JOIN departments d ON c.department_id = d.id
                          ORDER BY u.name, c.title";
            $course_data = getChartData($conn, $course_sql);
        }
        
        // Get chart data - Courses per university
        $chart_sql = "SELECT u.name, COUNT(*) as count 
                      FROM courses c
                      JOIN universities u ON c.university_id = u.id
                      GROUP BY c.university_id
                      ORDER BY count DESC";
        $chart_data = getChartData($conn, $chart_sql);
        break;
        
    case 'programs':
        // Build query based on filter
        if ($filter_uni > 0) {
            $program_sql = "SELECT p.*, u.name as university_name, d.name as department_name
                           FROM programs p
                           LEFT JOIN universities u ON p.university_id = u.id
                           LEFT JOIN departments d ON p.department_id = d.id
                           WHERE p.university_id = ?
                           ORDER BY p.program_name";
            $program_data = getChartData($conn, $program_sql, [$filter_uni]);
        } else {
            $program_sql = "SELECT p.*, u.name as university_name, d.name as department_name 
                           FROM programs p
                           LEFT JOIN universities u ON p.university_id = u.id
                           LEFT JOIN departments d ON p.department_id = d.id
                           ORDER BY u.name, p.program_name";
            $program_data = getChartData($conn, $program_sql);
        }
        
        // Get chart data - Programs per university
        $chart_sql = "SELECT u.name, COUNT(*) as count 
                      FROM programs p
                      JOIN universities u ON p.university_id = u.id
                      GROUP BY p.university_id
                      ORDER BY count DESC";
        $chart_data = getChartData($conn, $chart_sql);
        break;
}

// Get enrollment stats for all universities
$enrollment_sql = "SELECT u.name, 
                  (SELECT COUNT(*) FROM students s WHERE s.university_id = u.id) as student_count,
                  (SELECT COUNT(*) FROM departments d WHERE d.university_id = u.id) as dept_count,
                  (SELECT COUNT(*) FROM courses c WHERE c.university_id = u.id) as course_count,
                  (SELECT COUNT(*) FROM programs p WHERE p.university_id = u.id) as program_count
                  FROM universities u
                  ORDER BY student_count DESC";
$enrollment_data = getChartData($conn, $enrollment_sql);

// Function to generate CSV export
function generateCSV($data, $filename) {
    if (empty($data)) return;
    
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

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $export_type = $_GET['data_type'];
    $timestamp = date('Ymd_His');
    
    switch ($export_type) {
        case 'students':
            generateCSV($student_data, "student_data_$timestamp.csv");
            break;
        case 'departments':
            generateCSV($department_data, "department_data_$timestamp.csv");
            break;
        case 'courses':
            generateCSV($course_data, "course_data_$timestamp.csv");
            break;
        case 'programs':
            generateCSV($program_data, "program_data_$timestamp.csv");
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Explorer | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen">
    <!-- Header/Navigation -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between py-4">
                <div class="flex items-center">
                    <a href="../index.html" class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-800">EduDataSphere</span>
                    </a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="staff.php" class="px-3 py-2 text-gray-600 hover:text-blue-600">Dashboard</a>
                    <a href="data_explorer.php" class="px-3 py-2 text-blue-600 font-medium">Data Explorer</a>
                    
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
                            <a href="../profile/staff.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Your Profile</a>
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
                <a href="staff.php" class="block py-2 text-gray-600 hover:text-blue-600">Dashboard</a>
                <a href="data_explorer.php" class="block py-2 text-blue-600 font-medium">Data Explorer</a>
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <a href="../profile/staff.php" class="block py-2 text-gray-600 hover:text-blue-600">Your Profile</a>
                    <a href="../auth/logout.php" class="block py-2 text-gray-600 hover:text-blue-600">Sign out</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Title and Description -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Educational Data Explorer</h1>
            <p class="text-gray-600 mt-2">Access and analyze educational data across institutions.</p>
        </div>
        
        <!-- Filter Controls -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form action="data_explorer.php" method="get" class="flex flex-wrap items-end gap-4">
                <div class="w-full md:w-auto">
                    <label for="data_type" class="block text-sm font-medium text-gray-700 mb-1">Data Type</label>
                    <select id="data_type" name="data_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="students" <?php echo $data_type == 'students' ? 'selected' : ''; ?>>Students</option>
                        <option value="departments" <?php echo $data_type == 'departments' ? 'selected' : ''; ?>>Departments</option>
                        <option value="courses" <?php echo $data_type == 'courses' ? 'selected' : ''; ?>>Courses</option>
                        <option value="programs" <?php echo $data_type == 'programs' ? 'selected' : ''; ?>>Programs</option>
                    </select>
                </div>
                
                <div class="w-full md:w-auto">
                    <label for="university" class="block text-sm font-medium text-gray-700 mb-1">University</label>
                    <select id="university" name="university" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">All Universities</option>
                        <?php foreach ($universities as $uni): ?>
                            <option value="<?php echo $uni['id']; ?>" <?php echo $filter_uni == $uni['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($uni['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        Apply Filters
                    </button>
                </div>
                
                <?php 
                // Only show export button if we have data
                $has_data = false;
                switch ($data_type) {
                    case 'students': $has_data = !empty($student_data); break;
                    case 'departments': $has_data = !empty($department_data); break;
                    case 'courses': $has_data = !empty($course_data); break;
                    case 'programs': $has_data = !empty($program_data); break;
                }
                
                if ($has_data): 
                ?>
                <div class="ml-auto">
                    <a href="?data_type=<?php echo $data_type; ?>&university=<?php echo $filter_uni; ?>&export=1" 
                       class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export as CSV
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Data visualization content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Main content changes based on selected data type -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md">
                <?php if ($data_type == 'students' && !empty($student_data)): ?>
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-4">Student Data</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left bg-gray-50">
                                        <th class="px-4 py-2">Name</th>
                                        <th class="px-4 py-2">Email</th>
                                        <th class="px-4 py-2">University</th>
                                        <th class="px-4 py-2">Program</th>
                                        <th class="px-4 py-2">Registration Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($student_data as $student): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($student["name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($student["email"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($student["university_name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($student["program_name"] ?? "Not assigned"); ?></td>
                                            <td class="px-4 py-3"><?php echo date("M j, Y", strtotime($student["created_at"])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php elseif ($data_type == 'departments' && !empty($department_data)): ?>
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-4">Department Data</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left bg-gray-50">
                                        <th class="px-4 py-2">Name</th>
                                        <th class="px-4 py-2">University</th>
                                        <th class="px-4 py-2">Description</th>
                                        <th class="px-4 py-2">Head of Department</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($department_data as $dept): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($dept["name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($dept["university_name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($dept["description"] ?? ""); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($dept["head"] ?? "Not assigned"); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php elseif ($data_type == 'courses' && !empty($course_data)): ?>
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-4">Course Data</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left bg-gray-50">
                                        <th class="px-4 py-2">Title</th>
                                        <th class="px-4 py-2">Code</th>
                                        <th class="px-4 py-2">University</th>
                                        <th class="px-4 py-2">Department</th>
                                        <th class="px-4 py-2">Credits</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($course_data as $course): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($course["title"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($course["course_code"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($course["university_name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($course["department_name"] ?? "Not assigned"); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($course["credit_hours"] ?? ""); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php elseif ($data_type == 'programs' && !empty($program_data)): ?>
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-4">Program Data</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left bg-gray-50">
                                        <th class="px-4 py-2">Program Name</th>
                                        <th class="px-4 py-2">University</th>
                                        <th class="px-4 py-2">Department</th>
                                        <th class="px-4 py-2">Degree Level</th>
                                        <th class="px-4 py-2">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($program_data as $program): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($program["program_name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($program["university_name"]); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($program["department_name"] ?? "Not assigned"); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($program["degree_level"] ?? ""); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($program["duration"] ?? ""); ?> years</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="p-6 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500 mb-2">No data available</p>
                        <p class="text-gray-400 text-sm">Try selecting different filters</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Chart based on current data type -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Data Visualization</h2>
                
                <?php if (!empty($chart_data)): ?>
                    <div>
                        <h3 class="text-md font-medium mb-2">
                            <?php echo ucfirst($data_type); ?> per University
                        </h3>
                        <div class="h-64">
                            <canvas id="dataChart"></canvas>
                        </div>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('dataChart').getContext('2d');
                            
                            const chartData = {
                                labels: <?php echo json_encode(array_column($chart_data, 'name')); ?>,
                                datasets: [{
                                    label: '<?php echo ucfirst($data_type); ?>',
                                    data: <?php echo json_encode(array_column($chart_data, 'count')); ?>,
                                    backgroundColor: [
                                        'rgba(59, 130, 246, 0.7)',
                                        'rgba(16, 185, 129, 0.7)',
                                        'rgba(249, 115, 22, 0.7)',
                                        'rgba(139, 92, 246, 0.7)',
                                        'rgba(239, 68, 68, 0.7)',
                                        'rgba(236, 72, 153, 0.7)',
                                        'rgba(245, 158, 11, 0.7)',
                                        'rgba(37, 99, 235, 0.7)',
                                    ],
                                    borderColor: [
                                        'rgb(59, 130, 246)',
                                        'rgb(16, 185, 129)',
                                        'rgb(249, 115, 22)',
                                        'rgb(139, 92, 246)',
                                        'rgb(239, 68, 68)',
                                        'rgb(236, 72, 153)',
                                        'rgb(245, 158, 11)',
                                        'rgb(37, 99, 235)',
                                    ],
                                    borderWidth: 1
                                }]
                            };
                            
                            new Chart(ctx, {
                                type: 'bar',
                                data: chartData,
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                precision: 0
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p class="text-gray-500">No data available for visualization</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- University Enrollment Overview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">University Enrollment Overview</h2>
            
            <?php if (!empty($enrollment_data)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left bg-gray-50">
                                <th class="px-4 py-2">University</th>
                                <th class="px-4 py-2">Students</th>
                                <th class="px-4 py-2">Departments</th>
                                <th class="px-4 py-2">Courses</th>
                                <th class="px-4 py-2">Programs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($enrollment_data as $uni): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($uni["name"]); ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <span class="text-blue-600 font-semibold mr-2"><?php echo $uni["student_count"]; ?></span>
                                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($uni["student_count"] / max(1, max(array_column($enrollment_data, 'student_count')))) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <span class="text-green-600 font-semibold mr-2"><?php echo $uni["dept_count"]; ?></span>
                                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min(100, ($uni["dept_count"] / max(1, max(array_column($enrollment_data, 'dept_count')))) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <span class="text-purple-600 font-semibold mr-2"><?php echo $uni["course_count"]; ?></span>
                                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo min(100, ($uni["course_count"] / max(1, max(array_column($enrollment_data, 'course_count')))) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <span class="text-orange-600 font-semibold mr-2"><?php echo $uni["program_count"]; ?></span>
                                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-orange-600 h-2 rounded-full" style="width: <?php echo min(100, ($uni["program_count"] / max(1, max(array_column($enrollment_data, 'program_count')))) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-8">
                    <h3 class="text-md font-medium mb-2">Comparative Visualization</h3>
                    <div class="h-72">
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                </div>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const enrollCtx = document.getElementById('enrollmentChart').getContext('2d');
                        
                        const enrollData = {
                            labels: <?php echo json_encode(array_column($enrollment_data, 'name')); ?>,
                            datasets: [
                                {
                                    label: 'Students',
                                    data: <?php echo json_encode(array_column($enrollment_data, 'student_count')); ?>,
                                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                    borderColor: 'rgb(59, 130, 246)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Departments',
                                    data: <?php echo json_encode(array_column($enrollment_data, 'dept_count')); ?>,
                                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                    borderColor: 'rgb(16, 185, 129)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Courses',
                                    data: <?php echo json_encode(array_column($enrollment_data, 'course_count')); ?>,
                                    backgroundColor: 'rgba(139, 92, 246, 0.7)',
                                    borderColor: 'rgb(139, 92, 246)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Programs',
                                    data: <?php echo json_encode(array_column($enrollment_data, 'program_count')); ?>,
                                    backgroundColor: 'rgba(249, 115, 22, 0.7)',
                                    borderColor: 'rgb(249, 115, 22)',
                                    borderWidth: 1
                                }
                            ]
                        };
                        
                        new Chart(enrollCtx, {
                            type: 'bar',
                            data: enrollData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        });
                    });
                </script>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No enrollment data available</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4">
            <div class="text-center text-gray-500 text-sm">
                &copy; 2025 EduDataSphere. All rights reserved.
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

