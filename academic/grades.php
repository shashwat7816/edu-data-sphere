
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

// Get grades by semester
$grades_sql = "SELECT g.*, c.name as course_name, c.code as course_code, c.credits,
               s.name as semester_name, s.year
               FROM grades g
               JOIN courses c ON g.course_id = c.id
               JOIN semesters s ON g.semester_id = s.id
               WHERE g.student_id = ?
               ORDER BY s.year DESC, s.name DESC, c.name";
$grades_stmt = $conn->prepare($grades_sql);
$grades_stmt->bind_param("i", $student_id);
$grades_stmt->execute();
$grades_result = $grades_stmt->get_result();

// Group grades by semester
$semesters = [];
$gpa_by_semester = [];
$total_credits = 0;
$total_grade_points = 0;

while ($grade = $grades_result->fetch_assoc()) {
    $semester_key = $grade["semester_id"];
    $semester_name = $grade["semester_name"] . " " . $grade["year"];
    
    if (!isset($semesters[$semester_key])) {
        $semesters[$semester_key] = [
            "name" => $semester_name,
            "grades" => [],
            "total_credits" => 0,
            "total_grade_points" => 0
        ];
    }
    
    $semesters[$semester_key]["grades"][] = $grade;
    
    // Calculate grade points
    $grade_points = 0;
    switch ($grade["letter_grade"]) {
        case 'A+': $grade_points = 4.0; break;
        case 'A': $grade_points = 4.0; break;
        case 'A-': $grade_points = 3.7; break;
        case 'B+': $grade_points = 3.3; break;
        case 'B': $grade_points = 3.0; break;
        case 'B-': $grade_points = 2.7; break;
        case 'C+': $grade_points = 2.3; break;
        case 'C': $grade_points = 2.0; break;
        case 'C-': $grade_points = 1.7; break;
        case 'D+': $grade_points = 1.3; break;
        case 'D': $grade_points = 1.0; break;
        case 'F': $grade_points = 0.0; break;
        default: $grade_points = 0.0;
    }
    
    // Add to semester totals
    $course_grade_points = $grade_points * $grade["credits"];
    $semesters[$semester_key]["total_credits"] += $grade["credits"];
    $semesters[$semester_key]["total_grade_points"] += $course_grade_points;
    
    // Add to overall totals
    $total_credits += $grade["credits"];
    $total_grade_points += $course_grade_points;
}

// Calculate GPA for each semester
foreach ($semesters as $id => $semester) {
    if ($semester["total_credits"] > 0) {
        $semesters[$id]["gpa"] = round($semester["total_grade_points"] / $semester["total_credits"], 2);
    } else {
        $semesters[$id]["gpa"] = 0;
    }
}

// Calculate overall GPA
$overall_gpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Grades | EduDataSphere</title>
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
        <div class="max-w-5xl mx-auto">
            <h1 class="text-3xl font-bold mb-6">Academic Grades</h1>
            
            <!-- Student Info and Overall GPA -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-blue-600 px-6 py-4 text-white">
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></h2>
                    <p><?php echo htmlspecialchars($student["university_name"]); ?></p>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <p class="text-gray-500 mb-1">Overall GPA</p>
                            <p class="text-4xl font-bold text-blue-600"><?php echo number_format($overall_gpa, 2); ?></p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <p class="text-gray-500 mb-1">Total Credits</p>
                            <p class="text-4xl font-bold text-gray-600"><?php echo $total_credits; ?></p>
                        </div>
                        
                        <div class="bg-green-50 p-4 rounded-lg text-center">
                            <p class="text-gray-500 mb-1">Courses Completed</p>
                            <p class="text-4xl font-bold text-green-600"><?php echo count($grades_result->fetch_all(MYSQLI_ASSOC)); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grades by Semester -->
            <?php if (empty($semesters)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <p class="text-xl text-gray-600 mb-2">No grades available yet.</p>
                    <p class="text-gray-500">Your grades will appear here once they're recorded by your instructors.</p>
                </div>
            <?php else: ?>
                <?php foreach ($semesters as $semester): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                        <div class="bg-gray-50 px-6 py-4 flex justify-between items-center border-b">
                            <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($semester["name"]); ?></h2>
                            <div class="flex items-center">
                                <span class="text-gray-600 mr-2">Semester GPA:</span>
                                <span class="text-lg font-bold text-blue-600"><?php echo number_format($semester["gpa"], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credits</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($semester["grades"] as $grade): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($grade["course_name"]); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($grade["course_code"]); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $grade["credits"]; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $grade_class = "text-gray-600";
                                                if (in_array($grade["letter_grade"], ['A+', 'A', 'A-', 'B+', 'B'])) {
                                                    $grade_class = "text-green-600";
                                                } elseif (in_array($grade["letter_grade"], ['B-', 'C+', 'C'])) {
                                                    $grade_class = "text-yellow-600";
                                                } elseif (in_array($grade["letter_grade"], ['C-', 'D+', 'D'])) {
                                                    $grade_class = "text-orange-600";
                                                } elseif ($grade["letter_grade"] == 'F') {
                                                    $grade_class = "text-red-600";
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-100 <?php echo $grade_class; ?>">
                                                    <?php echo $grade["letter_grade"]; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $grade["numeric_grade"] ?? '-'; ?>/100
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($grade["remarks"] ?? ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- GPA Scale Reference -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-8">
                <h3 class="bg-gray-50 px-6 py-3 text-lg font-semibold border-b">GPA Scale Reference</h3>
                <div class="p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <div class="text-center p-2 rounded bg-green-50">
                            <p class="font-semibold text-green-600">A+/A</p>
                            <p class="text-sm text-gray-600">4.0</p>
                        </div>
                        <div class="text-center p-2 rounded bg-green-50">
                            <p class="font-semibold text-green-600">A-</p>
                            <p class="text-sm text-gray-600">3.7</p>
                        </div>
                        <div class="text-center p-2 rounded bg-green-50">
                            <p class="font-semibold text-green-600">B+</p>
                            <p class="text-sm text-gray-600">3.3</p>
                        </div>
                        <div class="text-center p-2 rounded bg-green-50">
                            <p class="font-semibold text-green-600">B</p>
                            <p class="text-sm text-gray-600">3.0</p>
                        </div>
                        <div class="text-center p-2 rounded bg-yellow-50">
                            <p class="font-semibold text-yellow-600">B-</p>
                            <p class="text-sm text-gray-600">2.7</p>
                        </div>
                        <div class="text-center p-2 rounded bg-yellow-50">
                            <p class="font-semibold text-yellow-600">C+</p>
                            <p class="text-sm text-gray-600">2.3</p>
                        </div>
                        <div class="text-center p-2 rounded bg-yellow-50">
                            <p class="font-semibold text-yellow-600">C</p>
                            <p class="text-sm text-gray-600">2.0</p>
                        </div>
                        <div class="text-center p-2 rounded bg-orange-50">
                            <p class="font-semibold text-orange-600">C-</p>
                            <p class="text-sm text-gray-600">1.7</p>
                        </div>
                        <div class="text-center p-2 rounded bg-orange-50">
                            <p class="font-semibold text-orange-600">D+</p>
                            <p class="text-sm text-gray-600">1.3</p>
                        </div>
                        <div class="text-center p-2 rounded bg-orange-50">
                            <p class="font-semibold text-orange-600">D</p>
                            <p class="text-sm text-gray-600">1.0</p>
                        </div>
                        <div class="text-center p-2 rounded bg-red-50">
                            <p class="font-semibold text-red-600">F</p>
                            <p class="text-sm text-gray-600">0.0</p>
                        </div>
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
