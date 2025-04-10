
<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
require_once('../database/db_config.php');

// Get user information based on role
$user_id = $_SESSION["user_id"];
$role = $_SESSION["user_role"];
$university_id = null;

// Get university_id based on role
if ($role === "student") {
    $sql = "SELECT university_id FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $university_id = $row["university_id"];
    }
} elseif ($role === "staff") {
    $sql = "SELECT university_id FROM university_staff WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $university_id = $row["university_id"];
    }
}

// Get university name
$university_name = "";
if ($university_id) {
    $uni_sql = "SELECT name FROM universities WHERE id = ?";
    $uni_stmt = $conn->prepare($uni_sql);
    $uni_stmt->bind_param("i", $university_id);
    $uni_stmt->execute();
    $uni_result = $uni_stmt->get_result();
    if ($uni_row = $uni_result->fetch_assoc()) {
        $university_name = $uni_row["name"];
    }
}

// Get course categories
$categories = [];
$cat_sql = "SELECT id, name FROM course_categories ORDER BY name";
$cat_result = $conn->query($cat_sql);
if ($cat_result && $cat_result->num_rows > 0) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get filter parameters
$category_filter = isset($_GET["category"]) ? $_GET["category"] : "";
$search_term = isset($_GET["search"]) ? $_GET["search"] : "";

// Build query based on filters
$courses_sql = "SELECT c.*, cc.name as category_name
                FROM courses c
                JOIN course_categories cc ON c.category_id = cc.id
                WHERE 1=1";

$params = [];
$param_types = "";

// Add university filter if not admin
if ($role !== "admin" && $university_id) {
    $courses_sql .= " AND c.university_id = ?";
    $params[] = $university_id;
    $param_types .= "i";
}

// Add category filter if specified
if (!empty($category_filter)) {
    $courses_sql .= " AND c.category_id = ?";
    $params[] = $category_filter;
    $param_types .= "i";
}

// Add search filter if specified
if (!empty($search_term)) {
    $courses_sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

// Add ordering
$courses_sql .= " ORDER BY c.name";

// Prepare and execute query
$courses = [];
$courses_stmt = $conn->prepare($courses_sql);

if (!empty($params)) {
    $courses_stmt->bind_param($param_types, ...$params);
}

$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

if ($courses_result && $courses_result->num_rows > 0) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Catalog | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen">
    <!-- Header Navigation -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="../dashboard/<?php echo $role; ?>.php" class="text-2xl font-bold text-blue-600">EduDataSphere</a>
                <div class="flex items-center space-x-4">
                    <a href="../profile/<?php echo $role; ?>.php" class="text-gray-700 hover:text-blue-600">
                        <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
                    </a>
                    <a href="../auth/logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Course Catalog</h1>
                <?php if ($role !== "admin" && !empty($university_name)): ?>
                    <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg">
                        <span class="font-semibold"><?php echo htmlspecialchars($university_name); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label for="search" class="block text-gray-700 text-sm font-bold mb-2">Search Courses</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" 
                            placeholder="Search by name or description" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="w-48">
                        <label for="category" class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                        <select id="category" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php if ($category_filter == $category['id']) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            Apply Filters
                        </button>
                        <a href="courses.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Course Listings -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($courses)): ?>
                    <div class="col-span-full bg-gray-50 p-8 rounded-lg text-center border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-500 mb-2">No Courses Found</h3>
                        <p class="text-gray-400">Try adjusting your search filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition feature-card">
                            <div class="bg-blue-600 h-3"></div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($course["name"]); ?></h3>
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($course["category_name"]); ?>
                                    </span>
                                </div>
                                
                                <p class="text-gray-600 mb-4">
                                    <?php echo nl2br(htmlspecialchars($course["description"])); ?>
                                </p>
                                
                                <div class="flex justify-between text-sm text-gray-500">
                                    <span>Credits: <?php echo $course["credits"]; ?></span>
                                    <span>Code: <?php echo htmlspecialchars($course["code"]); ?></span>
                                </div>
                                
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <?php if ($role === "student"): ?>
                                        <a href="course_registration.php?course_id=<?php echo $course["id"]; ?>" class="inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                                            Register for Course
                                        </a>
                                    <?php elseif ($role === "staff" || $role === "admin"): ?>
                                        <a href="course_details.php?id=<?php echo $course["id"]; ?>" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                                            View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
