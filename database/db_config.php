
<?php
// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "edu_data_sphere";

// First create a connection without database selected
$temp_conn = new mysqli($db_host, $db_user, $db_password);

// Check connection
if ($temp_conn->connect_error) {
    die("Connection failed: " . $temp_conn->connect_error);
}

// Check if database exists, if not create it
$check_db = $temp_conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
if ($check_db->num_rows == 0) {
    // Database doesn't exist, create it
    if ($temp_conn->query("CREATE DATABASE IF NOT EXISTS $db_name")) {
        // Import schema after database creation
        $temp_conn->select_db($db_name);
        
        // Read and execute the schema.sql file
        $schema_file = file_get_contents(__DIR__ . '/schema.sql');
        
        if ($schema_file) {
            // Split the schema file into individual queries
            $queries = explode(';', $schema_file);
            
            // Execute each query
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $temp_conn->query($query);
                }
            }
            
            // Log successful database creation
            error_log("Database $db_name created and schema imported successfully.");
        } else {
            error_log("Could not read schema file.");
        }
    } else {
        die("Error creating database: " . $temp_conn->error);
    }
} else {
    // Database exists, but we need to ensure the schema is up-to-date
    // Especially for columns like established_year which may be missing
    $temp_conn->select_db($db_name);
    
    // Check if the universities table has the established_year column
    $check_column = $temp_conn->query("SHOW COLUMNS FROM universities LIKE 'established_year'");
    if ($check_column->num_rows == 0) {
        // Column doesn't exist, add it
        $temp_conn->query("ALTER TABLE universities ADD COLUMN established_year VARCHAR(10) COMMENT 'Year university was established' AFTER website");
        error_log("Added established_year column to universities table.");
    }
    
    // Check if the universities table has the description column
    $check_column = $temp_conn->query("SHOW COLUMNS FROM universities LIKE 'description'");
    if ($check_column->num_rows == 0) {
        // Column doesn't exist, add it
        $temp_conn->query("ALTER TABLE universities ADD COLUMN description TEXT COMMENT 'University description' AFTER established_year");
        error_log("Added description column to universities table.");
    }
    
    // Check if the universities table has the accreditation column
    $check_column = $temp_conn->query("SHOW COLUMNS FROM universities LIKE 'accreditation'");
    if ($check_column->num_rows == 0) {
        // Column doesn't exist, add it
        $temp_conn->query("ALTER TABLE universities ADD COLUMN accreditation VARCHAR(255) COMMENT 'University accreditation information' AFTER description");
        error_log("Added accreditation column to universities table.");
    }
}

// Close temporary connection
$temp_conn->close();

// Create connection to the specific database
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");
?>
