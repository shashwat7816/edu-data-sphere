
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

// Initialize variables
$universities = [];
$message = "";

// Handle university creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create_university"])) {
    $name = trim($_POST["name"]);
    $location = trim($_POST["location"]);
    $website = trim($_POST["website"]);
    $accreditation = trim($_POST["accreditation"]);
    
    // Validate inputs
    if (empty($name) || empty($location)) {
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">University name and location are required.</div>';
    } else {
        // Check if university already exists
        $check_sql = "SELECT id FROM universities WHERE name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">A university with this name already exists.</div>';
        } else {
            // Insert new university
            $insert_sql = "INSERT INTO universities (name, location, website, accreditation, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssss", $name, $location, $website, $accreditation);
            
            if ($insert_stmt->execute()) {
                $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">University created successfully!</div>';
            } else {
                $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Error creating university: ' . $conn->error . '</div>';
            }
        }
    }
}

// Handle university deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_university"])) {
    $university_id = $_POST["university_id"];
    
    // Check if university has associated students or staff
    $check_sql = "SELECT 
                    (SELECT COUNT(*) FROM students WHERE university_id = ?) as student_count, 
                    (SELECT COUNT(*) FROM university_staff WHERE university_id = ?) as staff_count";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $university_id, $university_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $counts = $check_result->fetch_assoc();
    
    if ($counts["student_count"] > 0 || $counts["staff_count"] > 0) {
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                    Cannot delete university because it has associated students or staff members.
                    </div>';
    } else {
        // Delete university
        $delete_sql = "DELETE FROM universities WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $university_id);
        
        if ($delete_stmt->execute()) {
            $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">University deleted successfully!</div>';
        } else {
            $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Error deleting university: ' . $conn->error . '</div>';
        }
    }
}

// Get universities
$uni_sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM students WHERE university_id = u.id) as student_count,
            (SELECT COUNT(*) FROM university_staff WHERE university_id = u.id) as staff_count
            FROM universities u
            ORDER BY u.name";
$uni_result = $conn->query($uni_sql);

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
    <title>Manage Universities | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-[Inter] bg-gray-100 min-h-screen">
    <!-- Header Navigation -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="../dashboard/admin.php" class="text-2xl font-bold text-blue-600">EduDataSphere</a>
                <div class="flex items-center space-x-4">
                    <a href="../profile/admin.php" class="text-gray-700 hover:text-blue-600">
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
                <h1 class="text-3xl font-bold">Manage Universities</h1>
                <button id="openModal" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                    Add New University
                </button>
            </div>
            
            <?php echo $message; ?>
            
            <!-- Universities List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if (empty($universities)): ?>
                    <div class="p-8 text-center">
                        <p class="text-xl text-gray-600 mb-2">No universities found</p>
                        <p class="text-gray-500">Click the "Add New University" button to create one.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        University
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Location
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stats
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Accreditation
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($universities as $university): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($university["name"]); ?>
                                            </div>
                                            <?php if (!empty($university["website"])): ?>
                                                <div class="text-sm text-blue-600">
                                                    <a href="<?php echo htmlspecialchars($university["website"]); ?>" target="_blank" class="hover:underline">
                                                        <?php echo htmlspecialchars($university["website"]); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($university["location"]); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <span class="font-medium"><?php echo $university["student_count"]; ?></span> Students
                                            </div>
                                            <div class="text-sm text-gray-900">
                                                <span class="font-medium"><?php echo $university["staff_count"]; ?></span> Staff Members
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($university["accreditation"] ?? 'Not specified'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="edit_university.php?id=<?php echo $university["id"]; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                Edit
                                            </a>
                                            
                                            <?php if ($university["student_count"] == 0 && $university["staff_count"] == 0): ?>
                                                <form method="post" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this university?');">
                                                    <input type="hidden" name="university_id" value="<?php echo $university["id"]; ?>">
                                                    <button type="submit" name="delete_university" class="text-red-600 hover:text-red-900">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-gray-400 cursor-not-allowed" title="Cannot delete university with associated students or staff">
                                                    Delete
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Add University Modal -->
    <div id="universityModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-semibold">Add New University</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="post" action="" class="p-6">
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">University Name</label>
                    <input type="text" id="name" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label for="location" class="block text-gray-700 text-sm font-bold mb-2">Location</label>
                    <input type="text" id="location" name="location" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label for="website" class="block text-gray-700 text-sm font-bold mb-2">Website URL</label>
                    <input type="url" id="website" name="website" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Optional. Please include http:// or https://</p>
                </div>
                
                <div class="mb-6">
                    <label for="accreditation" class="block text-gray-700 text-sm font-bold mb-2">Accreditation</label>
                    <input type="text" id="accreditation" name="accreditation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Optional. Enter accreditation information.</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelModal" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md mr-2 hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit" name="create_university" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        Create University
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4">
            <p class="text-center text-gray-600">© 2025 EduDataSphere. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        // Modal functionality
        const modal = document.getElementById('universityModal');
        const openModalBtn = document.getElementById('openModal');
        const closeModalBtn = document.getElementById('closeModal');
        const cancelModalBtn = document.getElementById('cancelModal');
        
        openModalBtn.addEventListener('click', () => {
            modal.classList.remove('hidden');
        });
        
        closeModalBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
        
        cancelModalBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
