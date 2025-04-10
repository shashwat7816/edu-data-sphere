
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
$users = [];
$message = "";
$user_type = isset($_GET["type"]) ? $_GET["type"] : "students";
$search = isset($_GET["search"]) ? $_GET["search"] : "";

// Define valid user types
$valid_user_types = ["students", "staff", "admins"];
if (!in_array($user_type, $valid_user_types)) {
    $user_type = "students";
}

// Handle user activation/deactivation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_status"])) {
    $user_id = $_POST["user_id"];
    $type = $_POST["type"];
    $new_status = $_POST["new_status"];
    
    // Update user status
    $status_sql = "UPDATE $type SET status = ? WHERE id = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("si", $new_status, $user_id);
    
    if ($status_stmt->execute()) {
        $action = $new_status == 'active' ? 'activated' : 'deactivated';
        $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">User successfully ' . $action . '.</div>';
    } else {
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">Error updating user status: ' . $conn->error . '</div>';
    }
}

// Get users based on type
$query = "";
if ($user_type == "students") {
    $query = "SELECT s.*, u.name as university_name 
              FROM students s
              LEFT JOIN universities u ON s.university_id = u.id
              WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (s.name LIKE ? OR s.email LIKE ? OR s.aadhaar_number LIKE ?)";
    }
    
    $query .= " ORDER BY s.created_at DESC";
} elseif ($user_type == "staff") {
    $query = "SELECT s.*, u.name as university_name 
              FROM university_staff s
              LEFT JOIN universities u ON s.university_id = u.id
              WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (s.name LIKE ? OR s.email LIKE ? OR s.department LIKE ?)";
    }
    
    $query .= " ORDER BY s.created_at DESC";
} else {
    $query = "SELECT * FROM admins WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ?)";
    }
    
    $query .= " ORDER BY created_at DESC";
}

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    if ($user_type == "admins") {
        $stmt->bind_param("ss", $search_param, $search_param);
    } else {
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get user counts for navigation
$counts = [
    'students' => 0,
    'staff' => 0,
    'admins' => 0
];

$count_sql = "SELECT 
                (SELECT COUNT(*) FROM students) as students_count,
                (SELECT COUNT(*) FROM university_staff) as staff_count,
                (SELECT COUNT(*) FROM admins) as admins_count";
$count_result = $conn->query($count_sql);
if ($count_result && $row = $count_result->fetch_assoc()) {
    $counts['students'] = $row['students_count'];
    $counts['staff'] = $row['staff_count'];
    $counts['admins'] = $row['admins_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | EduDataSphere</title>
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
            <h1 class="text-3xl font-bold mb-6">Manage Users</h1>
            
            <?php echo $message; ?>
            
            <!-- User Type Tabs -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="flex">
                    <a href="?type=students" class="px-6 py-3 text-center flex-1 <?php echo $user_type == 'students' ? 'text-blue-600 border-b-2 border-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-800'; ?>">
                        Students
                        <span class="ml-2 bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['students']; ?></span>
                    </a>
                    <a href="?type=staff" class="px-6 py-3 text-center flex-1 <?php echo $user_type == 'staff' ? 'text-blue-600 border-b-2 border-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-800'; ?>">
                        University Staff
                        <span class="ml-2 bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['staff']; ?></span>
                    </a>
                    <a href="?type=admins" class="px-6 py-3 text-center flex-1 <?php echo $user_type == 'admins' ? 'text-blue-600 border-b-2 border-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-800'; ?>">
                        Administrators
                        <span class="ml-2 bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['admins']; ?></span>
                    </a>
                </div>
            </div>
            
            <!-- Search Bar -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex space-x-4">
                    <input type="hidden" name="type" value="<?php echo $user_type; ?>">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                            placeholder="Search by name, email<?php echo $user_type == 'students' ? ', or Aadhaar' : ($user_type == 'staff' ? ', or department' : ''); ?>" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            Search
                        </button>
                    </div>
                    <?php if (!empty($search)): ?>
                        <div>
                            <a href="?type=<?php echo $user_type; ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 inline-block transition">
                                Clear
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Users List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if (empty($users)): ?>
                    <div class="p-8 text-center">
                        <p class="text-xl text-gray-600 mb-2">No users found</p>
                        <p class="text-gray-500">
                            <?php if (!empty($search)): ?>
                                Try adjusting your search criteria.
                            <?php else: ?>
                                There are no <?php echo substr($user_type, 0, -1); ?>s in the system yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Name / Email
                                    </th>
                                    <?php if ($user_type != "admins"): ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            University
                                        </th>
                                    <?php endif; ?>
                                    <?php if ($user_type == "students"): ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Aadhaar
                                        </th>
                                    <?php elseif ($user_type == "staff"): ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Department
                                        </th>
                                    <?php endif; ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Joined
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                                    <?php echo strtoupper(substr($user["name"], 0, 1)); ?>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($user["name"]); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($user["email"]); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <?php if ($user_type != "admins"): ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($user["university_name"] ?? 'Not assigned'); ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <?php if ($user_type == "students"): ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php 
                                                    // Display last 4 digits of Aadhaar with masking
                                                    $aadhaar = $user["aadhaar_number"];
                                                    $masked_aadhaar = 'XXXX-XXXX-' . substr($aadhaar, -4);
                                                    echo $masked_aadhaar;
                                                    ?>
                                                </div>
                                            </td>
                                        <?php elseif ($user_type == "staff"): ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($user["department"] ?? 'Not specified'); ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $status = $user["status"] ?? 'active';
                                            $status_class = $status == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date("M j, Y", strtotime($user["created_at"])); ?>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form method="post" action="" class="inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                                <input type="hidden" name="type" value="<?php echo $user_type; ?>">
                                                <?php 
                                                $status = $user["status"] ?? 'active';
                                                $new_status = $status == 'active' ? 'inactive' : 'active';
                                                $btn_class = $status == 'active' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900';
                                                $btn_text = $status == 'active' ? 'Deactivate' : 'Activate';
                                                ?>
                                                <input type="hidden" name="new_status" value="<?php echo $new_status; ?>">
                                                <button type="submit" name="toggle_status" class="<?php echo $btn_class; ?>">
                                                    <?php echo $btn_text; ?>
                                                </button>
                                            </form>
                                            
                                            <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user["id"]; ?>" class="text-blue-600 hover:text-blue-900 ml-3">
                                                View
                                            </a>
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
    
    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4">
            <p class="text-center text-gray-600">© 2025 EduDataSphere. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
