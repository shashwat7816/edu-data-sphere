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
require_once('../utils/dashboard_functions.php');

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

// Get scholarship information
$scholarships_sql = "SELECT * FROM scholarships WHERE student_id = ?";
$scholarships_stmt = $conn->prepare($scholarships_sql);
$scholarships_stmt->bind_param("i", $student_id);
$scholarships_stmt->execute();
$scholarships_result = $scholarships_stmt->get_result();
$scholarships = [];
while ($row = $scholarships_result->fetch_assoc()) {
    $scholarships[] = $row;
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

// Get upcoming deadlines
$deadlines = getStudentDeadlines($conn, $student_id);

// Process document upload
$upload_message = "";
$upload_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["document"])) {
    $document_title = trim($_POST["document_title"]);
    $document_type = $_POST["document_type"];
    
    // Check if title is provided
    if (empty($document_title)) {
        $upload_error = "Please provide a document title";
    } else {
        $file = $_FILES["document"];
        
        // Check file size (max 2MB = 2097152 bytes)
        if ($file["size"] > 2097152) {
            $upload_error = "File size exceeds the 2MB limit";
        } else {
            // Check file type
            $allowed_types = ["application/pdf", "image/jpeg", "image/png"];
            $file_type = $file["type"];
            
            if (!in_array($file_type, $allowed_types)) {
                $upload_error = "Only PDF, JPEG, and PNG files are allowed";
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = "../uploads/documents/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename to prevent overwriting
                $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
                $file_name = uniqid() . "_" . $student_id . "." . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                // Move uploaded file
                if (move_uploaded_file($file["tmp_name"], $file_path)) {
                    // Save file information to database
                    $doc_sql = "INSERT INTO documents (student_id, title, document_type, file_path, file_size, file_type, upload_date) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $doc_stmt = $conn->prepare($doc_sql);
                    $file_size = $file["size"];
                    $doc_stmt->bind_param("isssss", $student_id, $document_title, $document_type, $file_name, $file_size, $file_type);
                    
                    if ($doc_stmt->execute()) {
                        $upload_message = "Document uploaded successfully!";
                        // Refresh page to show new document
                        header("Location: " . $_SERVER["PHP_SELF"]);
                        exit;
                    } else {
                        $upload_error = "Error saving document information: " . $conn->error;
                    }
                } else {
                    $upload_error = "Error uploading file";
                }
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
    <title>Student Dashboard | EduDataSphere</title>
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
                    <a href="../profile/student.php" class="px-3 py-2 text-gray-600 hover:text-blue-600">Profile</a>
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
                            <a href="../profile/student.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Your Profile</a>
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
                <a href="../profile/student.php" class="block py-2 text-gray-600 hover:text-blue-600">Profile</a>
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
        <!-- Welcome Banner -->
        <div class="bg-blue-600 rounded-lg shadow-md p-6 mb-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Welcome, <?php echo $_SESSION["user_name"]; ?>!</h1>
                    <p class="mt-1">Student at <?php echo $student["university_name"]; ?></p>
                </div>
                <div class="hidden md:block">
                    <div class="flex items-center bg-white/20 rounded-lg px-4 py-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span><?php echo date("F j, Y"); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <!-- Quick Stats -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Academic Summary</h2>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-500">Program</span>
                            <span class="text-sm font-medium"><?php echo $student["program"] ?? "Not specified"; ?></span>
                        </div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-500">Current Semester</span>
                            <span class="text-sm font-medium"><?php echo $student["current_semester"] ?? "Not specified"; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Enrollment Year</span>
                            <span class="text-sm font-medium"><?php echo $student["enrollment_year"] ?? "Not specified"; ?></span>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h3 class="text-md font-medium mb-2">GPA Overview</h3>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-500">Current CGPA</span>
                            <span class="text-sm font-medium">
                                <?php 
                                    $cgpa = $student["cgpa"] ?? 0;
                                    echo number_format($cgpa, 2);
                                ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($cgpa/10)*100); ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View full academic record →</a>
                </div>
            </div>
            
            <!-- Upcoming Events/Deadlines -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Upcoming Deadlines</h2>
                <?php if (count($deadlines) > 0): ?>
                    <ul class="space-y-3">
                        <?php foreach ($deadlines as $deadline): ?>
                            <li class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-sm font-medium"><?php echo date("d", strtotime($deadline["date"])); ?></span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium"><?php echo $deadline["title"]; ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo date("M j, Y", strtotime($deadline["date"])); ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-500">No upcoming deadlines</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View all deadlines →</a>
                </div>
            </div>
            
            <!-- Scholarships -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Scholarships</h2>
                <?php if (count($scholarships) > 0): ?>
                    <ul class="space-y-3">
                        <?php foreach ($scholarships as $scholarship): ?>
                            <li class="border-l-4 border-green-500 pl-3">
                                <h4 class="text-sm font-medium"><?php echo $scholarship["name"]; ?></h4>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-500">Amount: ₹<?php echo number_format($scholarship["amount"]); ?></span>
                                    <span class="<?php echo $scholarship["status"] === 'approved' ? 'text-green-500' : 'text-yellow-500'; ?>">
                                        <?php echo ucfirst($scholarship["status"]); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-500">No scholarships found</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">Explore available scholarships →</a>
                </div>
            </div>
        </div>
        
        <!-- Academic Performance & Documents -->
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Academic Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Academic Performance</h2>
                <?php if (count($academics) > 0): ?>
                    <div class="h-64">
                        <canvas id="academicChart"></canvas>
                    </div>
                    
                    <script>
                        // Prepare data for chart
                        const academics = <?php echo json_encode($academics); ?>;
                        
                        // Extract labels (semesters) and data (GPAs)
                        const labels = academics.map(item => `${item.year}-${item.semester}`);
                        const gpas = academics.map(item => item.gpa);
                        
                        // Create chart
                        const ctx = document.getElementById('academicChart').getContext('2d');
                        const academicChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'GPA',
                                    data: gpas,
                                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 2,
                                    tension: 0.1,
                                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 10,
                                        title: {
                                            display: true,
                                            text: 'GPA'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Semester'
                                        }
                                    }
                                }
                            }
                        });
                    </script>
                <?php else: ?>
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500">No academic records found</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Document Management -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Documents</h2>
                    <button id="uploadDocumentBtn" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Upload
                    </button>
                </div>
                
                <!-- Upload Document Modal -->
                <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                    <div class="bg-white rounded-lg max-w-md w-full p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Upload Document</h3>
                            <button id="closeModalBtn" class="text-gray-400 hover:text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <?php if (!empty($upload_error)): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                                <p><?php echo $upload_error; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" enctype="multipart/form-data">
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
                
                <!-- Document List -->
                <?php if (count($documents) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($documents as $index => $document): ?>
                            <?php if ($index < 5): // Show only the most recent 5 documents ?>
                                <div class="flex items-center justify-between bg-gray-50 rounded-md p-3">
                                    <div class="flex items-center">
                                        <?php 
                                        $icon = "document-text";
                                        if (strpos($document["file_type"], "pdf") !== false) {
                                            $icon = "document-text";
                                        } elseif (strpos($document["file_type"], "image") !== false) {
                                            $icon = "photograph";
                                        }
                                        ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <?php if ($icon === "document-text"): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            <?php endif; ?>
                                        </svg>
                                        <div>
                                            <h4 class="text-sm font-medium"><?php echo $document["title"]; ?></h4>
                                            <p class="text-xs text-gray-500">
                                                <?php echo ucfirst($document["document_type"]); ?> • 
                                                <?php echo date("M j, Y", strtotime($document["upload_date"])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <a href="../uploads/documents/<?php echo $document["file_path"]; ?>" class="text-blue-600 hover:text-blue-800" target="_blank">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($documents) > 5): ?>
                        <div class="mt-4 text-center">
                            <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View all documents (<?php echo count($documents); ?>) →</a>
                        </div>
                    <?php endif; ?>
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
        
        // Document upload modal
        const uploadDocumentBtn = document.getElementById('uploadDocumentBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const uploadModal = document.getElementById('uploadModal');
        
        if (uploadDocumentBtn && closeModalBtn && uploadModal) {
            uploadDocumentBtn.addEventListener('click', function() {
                uploadModal.classList.remove('hidden');
            });
            
            closeModalBtn.addEventListener('click', function() {
                uploadModal.classList.add('hidden');
            });
            
            // Close modal when clicking outside of it
            uploadModal.addEventListener('click', function(event) {
                if (event.target === uploadModal) {
                    uploadModal.classList.add('hidden');
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
