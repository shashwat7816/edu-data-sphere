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

// Get admin information
$admin_id = $_SESSION["user_id"];
$admin_sql = "SELECT * FROM admins WHERE id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();

// Get counts for dashboard
$counts = [];

// Total students
$student_count_sql = "SELECT COUNT(*) as count FROM students";
$student_result = $conn->query($student_count_sql);
$counts['students'] = $student_result->fetch_assoc()["count"];

// Total universities
$university_count_sql = "SELECT COUNT(*) as count FROM universities";
$university_result = $conn->query($university_count_sql);
$counts['universities'] = $university_result->fetch_assoc()["count"];

// Total departments
$dept_count_sql = "SELECT COUNT(*) as count FROM departments";
$dept_result = $conn->query($dept_count_sql);
$counts['departments'] = $dept_result->fetch_assoc()["count"];

// Total documents
$doc_count_sql = "SELECT COUNT(*) as count FROM documents";
$doc_result = $conn->query($doc_count_sql);
$counts['documents'] = $doc_result->fetch_assoc()["count"];

// Get recent activities (logins)
$activities_sql = "SELECT l.*, u.name, 'admin' as role 
                  FROM login_logs l 
                  JOIN admins u ON l.user_id = u.id 
                  WHERE l.role = 'admin'
                  UNION
                  SELECT l.*, u.name, 'staff' as role 
                  FROM login_logs l 
                  JOIN university_staff u ON l.user_id = u.id 
                  WHERE l.role = 'staff' 
                  UNION
                  SELECT l.*, u.name, 'student' as role 
                  FROM login_logs l 
                  JOIN students u ON l.user_id = u.id 
                  WHERE l.role = 'student' 
                  ORDER BY login_time DESC 
                  LIMIT 10";
$activities_result = $conn->query($activities_sql);
$activities = [];
if ($activities_result) {
    while ($row = $activities_result->fetch_assoc()) {
        $activities[] = $row;
    }
}

// Get demographics data for charts
// Gender distribution
$gender_sql = "SELECT gender, COUNT(*) as count FROM students GROUP BY gender";
$gender_result = $conn->query($gender_sql);
$gender_data = [];
if ($gender_result) {
    while ($row = $gender_result->fetch_assoc()) {
        $gender_data[] = $row;
    }
}

// University distribution
$university_sql = "SELECT u.name, COUNT(s.id) as count 
                  FROM universities u 
                  LEFT JOIN students s ON u.id = s.university_id 
                  GROUP BY u.id 
                  ORDER BY count DESC 
                  LIMIT 5";
$university_result = $conn->query($university_sql);
$university_data = [];
if ($university_result) {
    while ($row = $university_result->fetch_assoc()) {
        $university_data[] = $row;
    }
}

// Recent universities
$recent_unis_sql = "SELECT * FROM universities ORDER BY created_at DESC LIMIT 5";
$recent_unis_result = $conn->query($recent_unis_sql);
$recent_unis = [];
if ($recent_unis_result) {
    while ($row = $recent_unis_result->fetch_assoc()) {
        $recent_unis[] = $row;
    }
}

// Get system health metrics
$system_health = getSystemHealth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | EduDataSphere</title>
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
                    <a href="#" class="px-3 py-2 text-blue-600 font-medium">Dashboard</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Universities</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Students</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Analytics</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Reports</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Settings</a>
                    
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
                            <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Your Profile</a>
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
                <a href="#" class="block py-2 text-blue-600 font-medium">Dashboard</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Universities</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Students</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Analytics</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Reports</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Settings</a>
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <a href="../auth/logout.php" class="block py-2 text-gray-600 hover:text-blue-600">Sign out</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-md p-6 mb-6 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Welcome, <?php echo $_SESSION["user_name"]; ?>!</h1>
                    <p class="mt-1">Admin Dashboard | Educational Data Analytics</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <div class="flex items-center space-x-2">
                        <button class="bg-white text-blue-800 px-4 py-2 rounded-md hover:bg-blue-100 transition flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Export Reports
                        </button>
                        <button class="bg-blue-500 bg-opacity-40 text-white px-4 py-2 rounded-md hover:bg-opacity-60 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Students Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Students</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($counts['students']); ?></h3>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        View Details
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Universities Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Universities</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($counts['universities']); ?></h3>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        View Details
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Departments Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-purple-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Departments</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($counts['departments']); ?></h3>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        View Details
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Documents Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-yellow-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Documents</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($counts['documents']); ?></h3>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        View Details
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Dashboard Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Charts Section - Takes 2 columns on large screens -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Gender Distribution Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Student Demographics</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-medium mb-2 text-gray-500">Gender Distribution</h3>
                            <div class="h-64">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium mb-2 text-gray-500">Top Universities by Enrollment</h3>
                            <div class="h-64">
                                <canvas id="universityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Recent Activity</h2>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    
                    <?php if (count($activities) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($activities as $activity): ?>
                                <div class="flex items-start">
                                    <?php
                                        $color = "bg-gray-100 text-gray-600";
                                        if ($activity["role"] === "admin") {
                                            $color = "bg-red-100 text-red-600";
                                        } else if ($activity["role"] === "staff") {
                                            $color = "bg-green-100 text-green-600";
                                        } else {
                                            $color = "bg-blue-100 text-blue-600";
                                        }
                                    ?>
                                    <div class="rounded-full <?php echo $color; ?> p-2 mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?php if ($activity["role"] === "admin"): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            <?php elseif ($activity["role"] === "staff"): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            <?php endif; ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm">
                                            <span class="font-medium"><?php echo $activity["name"]; ?></span> 
                                            <span class="text-gray-500">logged in as 
                                                <span class="<?php 
                                                    if ($activity["role"] === "admin") echo "text-red-600";
                                                    elseif ($activity["role"] === "staff") echo "text-green-600";
                                                    else echo "text-blue-600";
                                                ?>">
                                                    <?php echo ucfirst($activity["role"]); ?>
                                                </span>
                                            </span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php 
                                                $login_time = strtotime($activity["login_time"]);
                                                echo date("M j, Y g:i A", $login_time); 
                                            ?>
                                            <span class="ml-2">from IP: <?php echo $activity["ip_address"]; ?></span>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">No recent activities found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar Content - Takes 1 column on large screens -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Quick Actions</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <button class="p-3 bg-blue-50 rounded-lg text-blue-700 hover:bg-blue-100 transition flex flex-col items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span class="text-sm">Add University</span>
                        </button>
                        <button class="p-3 bg-green-50 rounded-lg text-green-700 hover:bg-green-100 transition flex flex-col items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-sm">Add Staff</span>
                        </button>
                        <button class="p-3 bg-purple-50 rounded-lg text-purple-700 hover:bg-purple-100 transition flex flex-col items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <span class="text-sm">New Scholarship</span>
                        </button>
                        <button class="p-3 bg-yellow-50 rounded-lg text-yellow-700 hover:bg-yellow-100 transition flex flex-col items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm">Generate Report</span>
                        </button>
                    </div>
                </div>
                
                <!-- Recent Universities -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Recent Universities</h2>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    
                    <?php if (count($recent_unis) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_unis as $uni): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-medium mr-3">
                                            <?php echo strtoupper(substr($uni["name"], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <h4 class="font-medium"><?php echo $uni["name"]; ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo $uni["location"]; ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="inline-block px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                            Active
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">No universities found.</p>
                    <?php endif; ?>
                </div>
                
                <!-- System Health -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">System Health</h2>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm text-gray-500">Storage</span>
                                <span class="text-sm font-medium"><?php echo $system_health['storage']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $system_health['storage']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm text-gray-500">CPU Usage</span>
                                <span class="text-sm font-medium"><?php echo $system_health['cpu']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $system_health['cpu']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm text-gray-500">Memory</span>
                                <span class="text-sm font-medium"><?php echo $system_health['memory']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo $system_health['memory']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="pt-2 flex justify-between items-center">
                            <span class="text-sm font-medium text-green-600 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                All systems <?php echo $system_health['status']; ?>
                            </span>
                            <a href="#" class="text-sm text-blue-600 hover:text-blue-800">Details</a>
                        </div>
                    </div>
                </div>
            </div>
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
        
        // Gender Distribution Chart
        const genderData = <?php echo json_encode($gender_data); ?>;
        const genderLabels = genderData.map(item => item.gender || 'Not Specified');
        const genderCounts = genderData.map(item => item.count);
        const genderColors = ['#3B82F6', '#EC4899', '#8B5CF6', '#9CA3AF'];
        
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: genderLabels,
                datasets: [{
                    data: genderCounts,
                    backgroundColor: genderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // University Distribution Chart
        const universityData = <?php echo json_encode($university_data); ?>;
        const uniLabels = universityData.map(item => item.name);
        const uniCounts = universityData.map(item => item.count);
        
        const uniCtx = document.getElementById('universityChart').getContext('2d');
        const uniChart = new Chart(uniCtx, {
            type: 'bar',
            data: {
                labels: uniLabels,
                datasets: [{
                    label: 'Number of Students',
                    data: uniCounts,
                    backgroundColor: '#3B82F6',
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
