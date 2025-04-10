
<?php
// Start session
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
require_once('../database/db_config.php');

// Get student id
$student_id = $_SESSION["user_id"];

// Get course id from URL
$course_id = isset($_GET["course_id"]) ? $_GET["course_id"] : 0;

// Redirect if no course_id is provided
if ($course_id <= 0) {
    header("Location: courses.php");
    exit;
}

// Check if course exists
$course_sql = "SELECT c.*, u.name as university_name, cc.name as category_name 
               FROM courses c
               JOIN universities u ON c.university_id = u.id
               JOIN course_categories cc ON c.category_id = cc.id
               WHERE c.id = ?";
$course_stmt = $conn->prepare($course_sql);
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

if ($course_result->num_rows === 0) {
    // Course not found, redirect to courses page
    header("Location: courses.php");
    exit;
}

$course = $course_result->fetch_assoc();

// Get student information
$student_sql = "SELECT s.*, u.name as university_name 
                FROM students s
                JOIN universities u ON s.university_id = u.id
                WHERE s.id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();

// Check if student is from the same university as the course
if ($student["university_id"] != $course["university_id"]) {
    $error_message = "This course is not available for your university. Please select a course from your university.";
}

// Check if student is already registered for this course
$check_registration_sql = "SELECT * FROM course_registrations 
                         WHERE student_id = ? AND course_id = ? AND status != 'Dropped'";
$check_stmt = $conn->prepare($check_registration_sql);
$check_stmt->bind_param("ii", $student_id, $course_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

$already_registered = ($check_result->num_rows > 0);
$registration = $already_registered ? $check_result->fetch_assoc() : null;

// Process registration
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register"])) {
    // Check if student is from the same university as course
    if ($student["university_id"] != $course["university_id"]) {
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">This course is not available for your university.</div>';
    } 
    // Check if already registered
    elseif ($already_registered) {
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">You are already registered for this course.</div>';
    } 
    else {
        // Create registration
        $registration_sql = "INSERT INTO course_registrations (student_id, course_id, registration_date, status) 
                            VALUES (?, ?, NOW(), 'Pending')";
        $reg_stmt = $conn->prepare($registration_sql);
        $reg_stmt->bind_param("ii", $student_id, $course_id);
        
        if ($reg_stmt->execute()) {
            $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">Registration successful! Your registration status is pending approval.</div>';
            // Refresh registration status
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $already_registered = ($check_result->num_rows > 0);
            $registration = $already_registered ? $check_result->fetch_assoc() : null;
        } else {
            $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Registration failed: ' . $conn->error . '</div>';
        }
    }
}

// Process drop course
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["drop"])) {
    if (!$already_registered) {
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">You are not registered for this course.</div>';
    } else {
        // Update registration status
        $drop_sql = "UPDATE course_registrations SET status = 'Dropped', drop_date = NOW() 
                     WHERE student_id = ? AND course_id = ? AND status != 'Dropped'";
        $drop_stmt = $conn->prepare($drop_sql);
        $drop_stmt->bind_param("ii", $student_id, $course_id);
        
        if ($drop_stmt->execute()) {
            $message = '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">You have dropped this course.</div>';
            // Refresh registration status
            $already_registered = false;
            $registration = null;
        } else {
            $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Failed to drop course: ' . $conn->error . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registration | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen">
    <!-- Header Navigation -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="../dashboard/student.php" class="text-2xl font-bold text-blue-600">EduDataSphere</a>
                <div class="flex items-center space-x-4">
                    <a href="../profile/student.php" class="text-gray-700 hover:text-blue-600">
                        <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
                    </a>
                    <a href="../auth/logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center mb-6">
                <a href="courses.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    &larr; Back to Courses
                </a>
                <h1 class="text-3xl font-bold">Course Registration</h1>
            </div>
            
            <?php echo $message; ?>
            
            <!-- Course Information -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-blue-600 px-6 py-4">
                    <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($course["name"]); ?></h2>
                    <p class="text-blue-100">
                        <?php echo htmlspecialchars($course["code"]); ?> | 
                        <?php echo htmlspecialchars($course["category_name"]); ?>
                    </p>
                </div>
                
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold mb-3">Course Description</h3>
                        <p class="text-gray-700">
                            <?php echo nl2br(htmlspecialchars($course["description"])); ?>
                        </p>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Course Details</h3>
                            <ul class="space-y-2 text-gray-700">
                                <li><span class="font-medium">Credits:</span> <?php echo $course["credits"]; ?></li>
                                <li><span class="font-medium">University:</span> <?php echo htmlspecialchars($course["university_name"]); ?></li>
                                <li><span class="font-medium">Duration:</span> <?php echo htmlspecialchars($course["duration"] ?? 'Not specified'); ?></li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Prerequisites</h3>
                            <?php if (!empty($course["prerequisites"])): ?>
                                <p class="text-gray-700"><?php echo htmlspecialchars($course["prerequisites"]); ?></p>
                            <?php else: ?>
                                <p class="text-gray-500">No prerequisites specified.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Registration Action -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold mb-4">Registration Status</h3>
                        
                        <?php if ($already_registered): ?>
                            <div class="mb-4">
                                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4">
                                    <p class="font-medium">You are registered for this course</p>
                                    <p>Status: <span class="font-semibold"><?php echo $registration["status"]; ?></span></p>
                                    <p>Registered on: <?php echo date("F j, Y", strtotime($registration["registration_date"])); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($registration["status"] !== "Approved"): ?>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?course_id=" . $course_id); ?>">
                                    <button type="submit" name="drop" class="bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition">
                                        Drop Course
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?course_id=" . $course_id); ?>">
                                <?php if (!isset($error_message)): ?>
                                    <p class="text-gray-600 mb-4">Click the button below to register for this course.</p>
                                    <button type="submit" name="register" class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                                        Register Now
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
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
