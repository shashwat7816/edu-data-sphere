
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
