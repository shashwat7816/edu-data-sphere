
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

// Get student information
$student_id = $_SESSION["user_id"];
$student_sql = "SELECT s.*, u.name as university_name 
                FROM students s 
                JOIN universities u ON s.university_id = u.id 
                WHERE s.id = ?";

$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();

// Get academic records
$academics_sql = "SELECT * FROM academic_records WHERE student_id = ? ORDER BY year DESC, semester DESC";
$academics_stmt = $conn->prepare($academics_sql);
$academics_stmt->bind_param("i", $student_id);
$academics_stmt->execute();
$academics_result = $academics_stmt->get_result();
$academics = [];
while ($row = $academics_result->fetch_assoc()) {
    $academics[] = $row;
}

// Get documents
$documents_sql = "SELECT * FROM documents WHERE student_id = ? ORDER BY upload_date DESC";
$documents_stmt = $conn->prepare($documents_sql);
$documents_stmt->bind_param("i", $student_id);
$documents_stmt->execute();
$documents_result = $documents_stmt->get_result();
$documents = [];
while ($row = $documents_result->fetch_assoc()) {
    $documents[] = $row;
}

// Handle profile update
$update_message = "";
$update_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])) {
    // Get form data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $dob = $_POST["dob"];
    $address = trim($_POST["address"]);
    $bio = trim($_POST["bio"]);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $update_error = "Name and email are required fields";
    } else {
        // Check if email exists for another user
        $check_email_sql = "SELECT id FROM students WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_email_sql);
        $check_stmt->bind_param("si", $email, $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_error = "Email is already in use by another student";
        } else {
            // Update profile
            $update_sql = "UPDATE students SET name = ?, email = ?, phone = ?, date_of_birth = ?, address = ?, bio = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssi", $name, $email, $phone, $dob, $address, $bio, $student_id);
            
            if ($update_stmt->execute()) {
                $update_message = "Profile updated successfully!";
                // Update session variables
                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;
                
                // Refresh student data
                $stmt->execute();
                $student_result = $stmt->get_result();
                $student = $student_result->fetch_assoc();
            } else {
                $update_error = "Error updating profile: " . $conn->error;
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
    <title>Student Profile | EduDataSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <a href="../dashboard/student.php" class="px-3 py-2 text-gray-600 hover:text-blue-600">Dashboard</a>
                    <a href="#" class="px-3 py-2 text-blue-600 font-medium">Profile</a>
                    <a href="#" class="px-3 py-2 text-gray-600 hover:text-blue-600">Documents</a>
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
                <a href="../dashboard/student.php" class="block py-2 text-gray-600 hover:text-blue-600">Dashboard</a>
                <a href="#" class="block py-2 text-blue-600 font-medium">Profile</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Documents</a>
                <a href="#" class="block py-2 text-gray-600 hover:text-blue-600">Settings</a>
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <a href="../auth/logout.php" class="block py-2 text-gray-600 hover:text-blue-600">Sign out</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Student Profile</h1>
            <p class="text-gray-600">View and manage your personal information</p>
        </div>
        
        <?php if (!empty($update_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $update_message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($update_error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $update_error; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Profile Tabs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="flex border-b border-gray-200">
                <button class="tab-button active px-6 py-3 text-blue-600 border-b-2 border-blue-600 font-medium" data-tab="personal">
                    Personal Information
                </button>
                <button class="tab-button px-6 py-3 text-gray-500 hover:text-blue-600 font-medium" data-tab="academic">
                    Academic Records
                </button>
                <button class="tab-button px-6 py-3 text-gray-500 hover:text-blue-600 font-medium" data-tab="documents">
                    Documents
                </button>
                <button class="tab-button px-6 py-3 text-gray-500 hover:text-blue-600 font-medium" data-tab="settings">
                    Settings
                </button>
            </div>
            
            <!-- Personal Information Tab -->
            <div id="personal" class="tab-content p-6 active">
                <div class="mb-8 flex flex-col md:flex-row gap-8">
                    <!-- Profile Picture Section -->
                    <div class="md:w-1/3 mb-6 md:mb-0 text-center">
                        <div class="relative inline-block">
                            <?php if (!empty($student["profile_picture"])): ?>
                                <img src="../uploads/profile/<?php echo $student["profile_picture"]; ?>" alt="Profile Picture" class="w-40 h-40 rounded-full object-cover mx-auto">
                            <?php else: ?>
                                <div class="w-40 h-40 bg-blue-100 text-blue-600 text-5xl flex items-center justify-center rounded-full mx-auto">
                                    <?php echo substr($_SESSION["user_name"], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                            <button class="absolute bottom-2 right-2 bg-blue-600 text-white p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mt-4">
                            <h2 class="text-xl font-semibold"><?php echo $student["name"]; ?></h2>
                            <p class="text-sm text-gray-500">Student at <?php echo $student["university_name"]; ?></p>
                            <p class="text-sm text-gray-500">ID: <?php echo $student["student_id"] ?? "Not assigned"; ?></p>
                        </div>
                        
                        <div class="mt-6 border-t pt-4">
                            <h3 class="text-sm text-gray-500 mb-3">Account Status</h3>
                            <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Active
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Form -->
                    <div class="md:w-2/3">
                        <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo $student["name"]; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo $student["email"]; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo $student["phone"] ?? ""; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="dob" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                    <input type="date" id="dob" name="dob" value="<?php echo $student["date_of_birth"] ?? ""; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo $student["address"] ?? ""; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label for="aadhaar" class="block text-sm font-medium text-gray-700 mb-1">Aadhaar Number</label>
                                <input type="text" id="aadhaar" value="<?php echo substr($student["aadhaar_number"], 0, 8) . "XXXX"; ?>" class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" disabled>
                                <p class="text-xs text-gray-500 mt-1">Aadhaar number cannot be changed</p>
                            </div>
                            
                            <div class="mb-4">
                                <label for="university" class="block text-sm font-medium text-gray-700 mb-1">University</label>
                                <input type="text" id="university" value="<?php echo $student["university_name"]; ?>" class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" disabled>
                            </div>
                            
                            <div class="mb-6">
                                <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">About Me</label>
                                <textarea id="bio" name="bio" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $student["bio"] ?? ""; ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">Brief description about yourself</p>
                            </div>
                            
                            <div>
                                <input type="hidden" name="update_profile" value="1">
                                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Academic Records Tab -->
            <div id="academic" class="tab-content p-6 hidden">
                <div class="mb-6 flex justify-between items-center">
                    <h2 class="text-xl font-semibold">Academic History</h2>
                    <button class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Record
                    </button>
                </div>
                
                <?php if (count($academics) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left bg-gray-50">
                                    <th class="px-4 py-2">Year</th>
                                    <th class="px-4 py-2">Semester</th>
                                    <th class="px-4 py-2">Course</th>
                                    <th class="px-4 py-2">GPA</th>
                                    <th class="px-4 py-2">Credits</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($academics as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo $record["year"]; ?></td>
                                        <td class="px-4 py-3"><?php echo $record["semester"]; ?></td>
                                        <td class="px-4 py-3"><?php echo $record["course_name"]; ?></td>
                                        <td class="px-4 py-3">
                                            <span class="<?php echo $record["gpa"] >= 7.0 ? 'text-green-600' : 'text-yellow-600'; ?> font-medium">
                                                <?php echo number_format($record["gpa"], 2); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3"><?php echo $record["credits"]; ?></td>
                                        <td class="px-4 py-3">
                                            <?php 
                                                $status_color = "bg-gray-100 text-gray-800";
                                                if ($record["status"] === "passed") {
                                                    $status_color = "bg-green-100 text-green-800";
                                                } elseif ($record["status"] === "failed") {
                                                    $status_color = "bg-red-100 text-red-800";
                                                } elseif ($record["status"] === "ongoing") {
                                                    $status_color = "bg-blue-100 text-blue-800";
                                                }
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                                <?php echo ucfirst($record["status"]); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 border border-dashed border-gray-300 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                        </svg>
                        <p class="text-gray-500 mb-2">No academic records found</p>
                        <p class="text-gray-400 text-sm">Add your academic records to keep track of your progress</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-8">
                    <h3 class="text-lg font-semibold mb-4">Academic Summary</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Current CGPA</div>
                            <div class="text-2xl font-bold text-blue-600">
                                <?php 
                                    $cgpa = $student["cgpa"] ?? 0;
                                    echo number_format($cgpa, 2);
                                ?>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Total Credits</div>
                            <div class="text-2xl font-bold text-blue-600">
                                <?php
                                    $total_credits = 0;
                                    foreach ($academics as $record) {
                                        $total_credits += $record["credits"];
                                    }
                                    echo $total_credits;
                                ?>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Completed Semesters</div>
                            <div class="text-2xl font-bold text-blue-600">
                                <?php
                                    $completed_semesters = [];
                                    foreach ($academics as $record) {
                                        if ($record["status"] === "passed") {
                                            $sem_key = $record["year"] . "-" . $record["semester"];
                                            $completed_semesters[$sem_key] = true;
                                        }
                                    }
                                    echo count($completed_semesters);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents Tab -->
            <div id="documents" class="tab-content p-6 hidden">
                <div class="mb-6 flex justify-between items-center">
                    <h2 class="text-xl font-semibold">My Documents</h2>
                    <button id="uploadDocBtn" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Upload Document
                    </button>
                </div>
                
                <!-- Document Categories -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <button class="doc-filter-btn active px-3 py-1 rounded-full bg-blue-100 text-blue-600 text-sm" data-filter="all">
                        All Documents
                    </button>
                    <button class="doc-filter-btn px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 text-sm" data-filter="certificate">
                        Certificates
                    </button>
                    <button class="doc-filter-btn px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 text-sm" data-filter="transcript">
                        Transcripts
                    </button>
                    <button class="doc-filter-btn px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 text-sm" data-filter="identification">
                        Identification
                    </button>
                    <button class="doc-filter-btn px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 text-sm" data-filter="letter">
                        Letters
                    </button>
                    <button class="doc-filter-btn px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 text-sm" data-filter="other">
                        Other
                    </button>
                </div>
                
                <?php if (count($documents) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($documents as $document): ?>
                            <div class="document-card bg-white border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition" data-type="<?php echo $document["document_type"]; ?>">
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <?php 
                                        $icon_color = "text-blue-600";
                                        $icon = "document-text";
                                        if ($document["document_type"] === "certificate") {
                                            $icon_color = "text-green-600";
                                        } elseif ($document["document_type"] === "transcript") {
                                            $icon_color = "text-purple-600";
                                        } elseif ($document["document_type"] === "identification") {
                                            $icon_color = "text-red-600";
                                        }
                                        ?>
                                        <div class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 <?php echo $icon_color; ?> mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <?php if ($icon === "document-text"): ?>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                <?php else: ?>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                <?php endif; ?>
                                            </svg>
                                            <span class="font-medium"><?php echo $document["title"]; ?></span>
                                        </div>
                                        <div>
                                            <?php 
                                                $status_class = "bg-yellow-100 text-yellow-800";
                                                $status_text = "Pending";
                                                
                                                if ($document["approval_status"] === "approved") {
                                                    $status_class = "bg-green-100 text-green-800";
                                                    $status_text = "Approved";
                                                } elseif ($document["approval_status"] === "rejected") {
                                                    $status_class = "bg-red-100 text-red-800";
                                                    $status_text = "Rejected";
                                                }
                                            ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="text-sm text-gray-500 mb-4">
                                        <p>Type: <?php echo ucfirst($document["document_type"]); ?></p>
                                        <p>Uploaded: <?php echo date("M j, Y", strtotime($document["upload_date"])); ?></p>
                                        <p>Size: <?php echo round($document["file_size"] / 1024, 2); ?> KB</p>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <a href="../uploads/documents/<?php echo $document["file_path"]; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View
                                        </a>
                                        <a href="../uploads/documents/<?php echo $document["file_path"]; ?>" download class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 border border-dashed border-gray-300 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500 mb-2">No documents uploaded yet</p>
                        <p class="text-gray-400 text-sm">Upload your certificates, transcripts, and other important documents</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Settings Tab -->
            <div id="settings" class="tab-content p-6 hidden">
                <h2 class="text-xl font-semibold mb-6">Account Settings</h2>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="font-medium mb-2">Change Password</h3>
                    <form>
                        <div class="mb-4">
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                            Update Password
                        </button>
                    </form>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="font-medium mb-2">Notification Preferences</h3>
                    <form>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label for="email_notifications" class="text-sm text-gray-700">Email Notifications</label>
                                <div>
                                    <input type="checkbox" id="email_notifications" name="email_notifications" class="form-checkbox h-4 w-4 text-blue-600" checked>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <label for="document_updates" class="text-sm text-gray-700">Document Status Updates</label>
                                <div>
                                    <input type="checkbox" id="document_updates" name="document_updates" class="form-checkbox h-4 w-4 text-blue-600" checked>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <label for="academic_updates" class="text-sm text-gray-700">Academic Updates</label>
                                <div>
                                    <input type="checkbox" id="academic_updates" name="academic_updates" class="form-checkbox h-4 w-4 text-blue-600" checked>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <label for="scholarship_alerts" class="text-sm text-gray-700">Scholarship Alerts</label>
                                <div>
                                    <input type="checkbox" id="scholarship_alerts" name="scholarship_alerts" class="form-checkbox h-4 w-4 text-blue-600" checked>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                            Save Preferences
                        </button>
                    </form>
                </div>
                
                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                    <h3 class="font-medium text-red-700 mb-2">Danger Zone</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Once you delete your account, there is no going back. Please be certain.
                    </p>
                    <button class="text-red-600 border border-red-600 py-2 px-4 rounded-md hover:bg-red-600 hover:text-white transition">
                        Delete Account
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Upload Document Modal -->
    <div id="uploadDocModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg max-w-md w-full p-6 mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Upload Document</h3>
                <button id="closeDocModalBtn" class="text-gray-400 hover:text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form action="../dashboard/upload_document.php" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="document_title" class="block text-gray-700 text-sm font-bold mb-2">Document Title</label>
                    <input type="text" id="document_title" name="document_title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label for="document_type" class="block text-gray-700 text-sm font-bold mb-2">Document Type</label>
                    <select id="document_type" name="document_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Type</option>
                        <option value="certificate">Certificate</option>
                        <option value="transcript">Transcript</option>
                        <option value="identification">Identification</option>
                        <option value="letter">Letter</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="document" class="block text-gray-700 text-sm font-bold mb-2">Upload File</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <input type="file" id="document" name="document" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                        <label for="document" class="cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="mt-1 text-sm text-gray-600">Click to browse or drag and drop</p>
                            <p class="mt-1 text-xs text-gray-500">PDF, JPG, PNG (Max 2MB)</p>
                        </label>
                        <p id="selected-file" class="mt-2 text-sm text-blue-600"></p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        Upload Document
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
        
        // Tab switching
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => {
                    btn.classList.remove('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                    btn.classList.add('text-gray-500');
                });
                
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                    content.classList.remove('active');
                });
                
                // Add active class to clicked button and corresponding content
                const tab = this.getAttribute('data-tab');
                this.classList.add('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                this.classList.remove('text-gray-500');
                
                const activeContent = document.getElementById(tab);
                activeContent.classList.remove('hidden');
                activeContent.classList.add('active');
            });
        });
        
        // Document filtering
        const docFilterButtons = document.querySelectorAll('.doc-filter-btn');
        const documentCards = document.querySelectorAll('.document-card');
        
        docFilterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all filter buttons
                docFilterButtons.forEach(btn => {
                    btn.classList.remove('active', 'bg-blue-100', 'text-blue-600');
                    btn.classList.add('bg-gray-100', 'text-gray-600');
                });
                
                // Add active class to clicked button
                this.classList.add('active', 'bg-blue-100', 'text-blue-600');
                this.classList.remove('bg-gray-100', 'text-gray-600');
                
                // Filter documents
                const filter = this.getAttribute('data-filter');
                
                documentCards.forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-type') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Upload document modal
        const uploadDocBtn = document.getElementById('uploadDocBtn');
        const closeDocModalBtn = document.getElementById('closeDocModalBtn');
        const uploadDocModal = document.getElementById('uploadDocModal');
        
        if (uploadDocBtn && closeDocModalBtn && uploadDocModal) {
            uploadDocBtn.addEventListener('click', function() {
                uploadDocModal.classList.remove('hidden');
            });
            
            closeDocModalBtn.addEventListener('click', function() {
                uploadDocModal.classList.add('hidden');
            });
            
            // Close modal when clicking outside
            uploadDocModal.addEventListener('click', function(event) {
                if (event.target === uploadDocModal) {
                    uploadDocModal.classList.add('hidden');
                }
            });
        }
        
        // Display selected filename
        const fileInput = document.getElementById('document');
        const fileNameDisplay = document.getElementById('selected-file');
        
        if (fileInput && fileNameDisplay) {
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    fileNameDisplay.textContent = "Selected: " + fileInput.files[0].name;
                } else {
                    fileNameDisplay.textContent = "";
                }
            });
        }
    </script>
</body>
</html>
