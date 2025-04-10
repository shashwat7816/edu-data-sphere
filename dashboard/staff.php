<?php
// Start session
session_start();

// Check if user is logged in and is a university staff
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

// Get student count
$student_count_sql = "SELECT COUNT(*) as count FROM students WHERE university_id = ?";
$student_count_stmt = $conn->prepare($student_count_sql);
$student_count_stmt->bind_param("i", $university_id);
$student_count_stmt->execute();
$student_count_result = $student_count_stmt->get_result();
$student_count = $student_count_result->fetch_assoc()["count"];

// Get department count
$dept_count_sql = "SELECT COUNT(*) as count FROM departments WHERE university_id = ?";
$dept_count_stmt = $conn->prepare($dept_count_sql);
$dept_count_stmt->bind_param("i", $university_id);
$dept_count_stmt->execute();
$dept_count_result = $dept_count_stmt->get_result();
$dept_count = $dept_count_result->fetch_assoc()["count"];

// Get course count
$course_count_sql = "SELECT COUNT(*) as count FROM courses WHERE university_id = ?";
$course_count_stmt = $conn->prepare($course_count_sql);
$course_count_stmt->bind_param("i", $university_id);
$course_count_stmt->execute();
$course_count_result = $course_count_stmt->get_result();
$course_count = $course_count_result->fetch_assoc()["count"];

// Get recent students
$students_sql = "SELECT s.*, p.program_name 
                FROM students s 
                LEFT JOIN programs p ON s.program_id = p.id
                WHERE s.university_id = ? 
                ORDER BY s.created_at DESC 
                LIMIT 5";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("i", $university_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}

// Get pending approvals
$pending_sql = "SELECT s.id, s.name, s.email, d.id as document_id, d.title, d.document_type, d.upload_date, d.file_path
                FROM documents d
                JOIN students s ON d.student_id = s.id
                WHERE s.university_id = ? AND d.approval_status = 'pending'
                ORDER BY d.upload_date DESC";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $university_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_docs = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_docs[] = $row;
}

// Process document approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["document_id"])) {
    $document_id = $_POST["document_id"];
    $action = $_POST["action"];
    $status = ($action == "approve") ? "approved" : "rejected";
    
    $update_sql = "UPDATE documents SET approval_status = ?, approval_date = NOW(), approved_by = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $status, $staff_id, $document_id);
    
    if ($update_stmt->execute()) {
        // Set a success message
        $action_message = "Document has been " . $status;
    } else {
        // Set an error message
        $action_error = "Error updating document status: " . $conn->error;
    }
}

// Display notice added message if redirected after adding a notice
if (isset($_GET['notice_added']) && $_GET['notice_added'] == '1') {
    $action_message = "Notice has been published successfully!";
} else if (isset($_GET['error']) && !empty($_GET['error'])) {
    $action_error = $_GET['error'];
}

// Get university notices
$notices = getUniversityNotices($conn, $university_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Staff Dashboard | EduDataSphere</title>
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
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Students</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Courses</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Departments</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Notices</a>
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
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Students</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Courses</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Departments</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Notices</a>
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
        <div class="bg-blue-600 rounded-lg shadow-md p-6 mb-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Welcome, <?php echo $_SESSION["user_name"]; ?>!</h1>
                    <p class="mt-1">Government Staff at <?php echo $staff["university_name"]; ?></p>
                </div>
                <div class="hidden md:block">
                    <div class="flex items-center bg-white/20 rounded-lg px-4 py-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span><?php echo date("F j, Y"); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Students Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Total Students</h3>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $student_count; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View all students →</a>
                </div>
            </div>
            
            <!-- Departments Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Departments</h3>
                        <p class="text-3xl font-bold text-green-600"><?php echo $dept_count; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">Manage departments →</a>
                </div>
            </div>
            
            <!-- Courses Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-purple-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Courses</h3>
                        <p class="text-3xl font-bold text-purple-600"><?php echo $course_count; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">Manage courses →</a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Students -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Recent Students</h2>
                
                <?php if (count($students) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left bg-gray-50">
                                    <th class="px-4 py-2">Name</th>
                                    <th class="px-4 py-2">Email</th>
                                    <th class="px-4 py-2">Program</th>
                                    <th class="px-4 py-2">Joined</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($students as $student): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo $student["name"]; ?></td>
                                        <td class="px-4 py-3"><?php echo $student["email"]; ?></td>
                                        <td class="px-4 py-3"><?php echo $student["program_name"] ?? "Not assigned"; ?></td>
                                        <td class="px-4 py-3"><?php echo date("M j, Y", strtotime($student["created_at"])); ?></td>
                                        <td class="px-4 py-3">
                                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View all students →</a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-gray-500">No students have been registered yet</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- University Notices -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">University Notices</h2>
                    <button id="addNoticeBtn" class="px-2 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New
                    </button>
                </div>
                
                <?php if (count($notices) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($notices as $notice): ?>
                            <div class="border-l-4 border-blue-500 pl-3 py-1">
                                <h3 class="font-medium text-gray-800"><?php echo $notice["title"]; ?></h3>
                                <p class="text-sm text-gray-600 mb-1"><?php echo substr($notice["content"], 0, 100) . (strlen($notice["content"]) > 100 ? "..." : ""); ?></p>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span><?php echo date("M j, Y", strtotime($notice["created_at"])); ?></span>
                                    <a href="#" class="text-blue-600">Read more</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View all notices →</a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                        </svg>
                        <p class="text-gray-500 mb-2">No notices have been posted yet</p>
                        <p class="text-gray-400 text-sm">All documents have been reviewed</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Approval Requests -->
            <div class="lg:col-span-3 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Pending Approvals</h2>
                
                <?php if (!empty($action_message)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                        <p><?php echo $action_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($action_error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo $action_error; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (count($pending_docs) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left bg-gray-50">
                                    <th class="px-4 py-2">Student</th>
                                    <th class="px-4 py-2">Email</th>
                                    <th class="px-4 py-2">Document Title</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2">Uploaded</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($pending_docs as $doc): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo $doc["name"]; ?></td>
                                        <td class="px-4 py-3"><?php echo $doc["email"]; ?></td>
                                        <td class="px-4 py-3"><?php echo $doc["title"]; ?></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo ucfirst($doc["document_type"]); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3"><?php echo date("M j, Y", strtotime($doc["upload_date"])); ?></td>
                                        <td class="px-4 py-3 flex space-x-2">
                                            <a href="../uploads/documents/<?php echo $doc["file_path"] ?? '#'; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                View
                                            </a>
                                            <form method="post" action="" class="inline">
                                                <input type="hidden" name="document_id" value="<?php echo $doc["document_id"]; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="text-green-600 hover:text-green-800">Approve</button>
                                            </form>
                                            <form method="post" action="" class="inline">
                                                <input type="hidden" name="document_id" value="<?php echo $doc["document_id"]; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="text-red-600 hover:text-red-800">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 border border-dashed border-gray-300 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-500 mb-2">No pending approvals</p>
                        <p class="text-gray-400 text-sm">All documents have been reviewed</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Notice Modal -->
    <div id="noticeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg max-w-md w-full p-6 mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Add New Notice</h3>
                <button id="closeNoticeModalBtn" class="text-gray-400 hover:text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form action="add_notice.php" method="post">
                <div class="mb-4">
                    <label for="notice_title" class="block text-gray-700 text-sm font-bold mb-2">Notice Title</label>
                    <input type="text" id="notice_title" name="notice_title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label for="notice_content" class="block text-gray-700 text-sm font-bold mb-2">Content</label>
                    <textarea id="notice_content" name="notice_content" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="notice_type" class="block text-gray-700 text-sm font-bold mb-2">Notice Type</label>
                    <select id="notice_type" name="notice_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Type</option>
                        <option value="announcement">Announcement</option>
                        <option value="deadline">Deadline</option>
                        <option value="event">Event</option>
                        <option value="academic">Academic</option>
                    </select>
                </div>
                
                <input type="hidden" name="university_id" value="<?php echo $university_id; ?>">
                
                <div class="mt-6">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        Publish Notice
                    </button>
                </div>
            </form>
        </div>
    </div>

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
        
        // Notice modal
        const addNoticeBtn = document.getElementById('addNoticeBtn');
        const closeNoticeModalBtn = document.getElementById('closeNoticeModalBtn');
        const noticeModal = document.getElementById('noticeModal');
        
        if (addNoticeBtn && closeNoticeModalBtn && noticeModal) {
            addNoticeBtn.addEventListener('click', function() {
                noticeModal.classList.remove('hidden');
            });
            
            closeNoticeModalBtn.addEventListener('click', function() {
                noticeModal.classList.add('hidden');
            });
            
            // Close modal when clicking outside of it
            noticeModal.addEventListener('click', function(event) {
                if (event.target === noticeModal) {
                    noticeModal.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>
