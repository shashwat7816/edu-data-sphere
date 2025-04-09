
-- Create Database
CREATE DATABASE IF NOT EXISTS edu_data_sphere;
USE edu_data_sphere;

-- Universities Table
CREATE TABLE IF NOT EXISTS universities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    location VARCHAR(255) NOT NULL,
    address TEXT,
    website VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    founded_year INT,
    type ENUM('public', 'private', 'deemed') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    head_name VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
);

-- Programs Table
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    university_id INT NOT NULL,
    program_name VARCHAR(255) NOT NULL,
    program_code VARCHAR(50) NOT NULL,
    degree_level ENUM('bachelors', 'masters', 'doctoral', 'diploma', 'certificate') NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in semesters',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
);

-- Courses Table
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    university_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    course_code VARCHAR(50) NOT NULL,
    credits INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
);

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    designation VARCHAR(100),
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- University Staff Table
CREATE TABLE IF NOT EXISTS university_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    designation VARCHAR(100),
    department_id INT,
    employee_id VARCHAR(50),
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Students Table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    aadhaar_number VARCHAR(12) NOT NULL UNIQUE,
    student_id VARCHAR(50),
    university_id INT NOT NULL,
    program_id INT,
    enrollment_year INT,
    current_semester INT,
    cgpa DECIMAL(4,2) DEFAULT 0.00,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    bio TEXT,
    profile_picture VARCHAR(255),
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL
);

-- Academic Records Table
CREATE TABLE IF NOT EXISTS academic_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    year INT NOT NULL,
    semester INT NOT NULL,
    course_id INT,
    course_name VARCHAR(255) NOT NULL,
    credits INT NOT NULL,
    gpa DECIMAL(4,2) NOT NULL,
    status ENUM('ongoing', 'passed', 'failed', 'withdrawn') DEFAULT 'ongoing',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Documents Table
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    document_type ENUM('certificate', 'transcript', 'identification', 'letter', 'other') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approval_date DATETIME,
    approved_by INT,
    remarks TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES university_staff(id) ON DELETE SET NULL
);

-- Scholarships Table
CREATE TABLE IF NOT EXISTS scholarships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    student_id INT NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'disbursed') DEFAULT 'pending',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decision_date DATETIME,
    decided_by INT,
    remarks TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (decided_by) REFERENCES university_staff(id) ON DELETE SET NULL
);

-- Notices Table
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    notice_type ENUM('announcement', 'deadline', 'event', 'academic') NOT NULL,
    published_by INT NOT NULL,
    published_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    FOREIGN KEY (published_by) REFERENCES university_staff(id) ON DELETE CASCADE
);

-- Login Logs Table
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('admin', 'staff', 'student') NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME,
    ip_address VARCHAR(50),
    user_agent TEXT
);

-- Sample Data Insertion

-- Sample Universities
INSERT INTO universities (name, code, location, website, type)
VALUES 
('Delhi University', 'DU', 'Delhi', 'www.du.ac.in', 'public'),
('Mumbai University', 'MU', 'Mumbai', 'www.mu.ac.in', 'public'),
('Indian Institute of Technology, Bombay', 'IITB', 'Mumbai', 'www.iitb.ac.in', 'public'),
('Anna University', 'AU', 'Chennai', 'www.annauniv.edu', 'public'),
('Manipal Academy of Higher Education', 'MAHE', 'Manipal', 'www.manipal.edu', 'private');

-- Sample Admin
INSERT INTO admins (name, email, password, designation)
VALUES ('Admin User', 'admin@edudatasphere.com', '$2y$10$TCPIOKUxQqGHPQKBGbFQbOXVn5e7vQhLMrQTx8TCMiYJ.3gS2CVae', 'System Administrator');
-- Password: admin123

-- Sample Staff for each university
INSERT INTO university_staff (university_id, name, email, password, designation)
VALUES 
(1, 'Staff Delhi', 'staff_delhi@edudatasphere.com', '$2y$10$mO./hx4mSxDaFgCnJVxSduKKR3qdY6Pj73ZC7HA0YHteV.uAWqlIi', 'Registrar'),
(2, 'Staff Mumbai', 'staff_mumbai@edudatasphere.com', '$2y$10$mO./hx4mSxDaFgCnJVxSduKKR3qdY6Pj73ZC7HA0YHteV.uAWqlIi', 'Admissions Officer'),
(3, 'Staff IIT', 'staff_iit@edudatasphere.com', '$2y$10$mO./hx4mSxDaFgCnJVxSduKKR3qdY6Pj73ZC7HA0YHteV.uAWqlIi', 'Department Head'),
(4, 'Staff Anna', 'staff_anna@edudatasphere.com', '$2y$10$mO./hx4mSxDaFgCnJVxSduKKR3qdY6Pj73ZC7HA0YHteV.uAWqlIi', 'Academic Coordinator'),
(5, 'Staff Manipal', 'staff_manipal@edudatasphere.com', '$2y$10$mO./hx4mSxDaFgCnJVxSduKKR3qdY6Pj73ZC7HA0YHteV.uAWqlIi', 'Administrative Officer');
-- Password: staff123

-- Sample Departments for Delhi University
INSERT INTO departments (university_id, name, code)
VALUES 
(1, 'Computer Science', 'CS'),
(1, 'Electronics', 'EC'),
(1, 'Mechanical Engineering', 'ME'),
(2, 'Computer Science', 'CS'),
(2, 'Economics', 'ECON'),
(3, 'Computer Science and Engineering', 'CSE'),
(3, 'Electrical Engineering', 'EE');

-- Sample Programs
INSERT INTO programs (department_id, university_id, program_name, program_code, degree_level, duration)
VALUES 
(1, 1, 'Bachelor of Technology in Computer Science', 'BTCS', 'bachelors', 8),
(2, 1, 'Bachelor of Technology in Electronics', 'BTEC', 'bachelors', 8),
(3, 1, 'Bachelor of Technology in Mechanical', 'BTME', 'bachelors', 8),
(4, 2, 'Bachelor of Science in Computer Science', 'BSCS', 'bachelors', 6),
(5, 2, 'Master of Arts in Economics', 'MAECON', 'masters', 4),
(6, 3, 'Bachelor of Technology in Computer Science', 'BTCSE', 'bachelors', 8),
(7, 3, 'Master of Technology in Electrical Engineering', 'MTEE', 'masters', 4);

-- Sample Courses
INSERT INTO courses (program_id, university_id, course_name, course_code, credits)
VALUES 
(1, 1, 'Introduction to Programming', 'CS101', 4),
(1, 1, 'Data Structures', 'CS201', 4),
(2, 1, 'Digital Electronics', 'EC101', 4),
(3, 1, 'Engineering Mechanics', 'ME101', 4),
(4, 2, 'Database Management Systems', 'CS301', 3),
(5, 2, 'Microeconomics', 'ECON101', 3),
(6, 3, 'Algorithms', 'CSE201', 4),
(7, 3, 'Power Systems', 'EE301', 4);

-- Sample Students
INSERT INTO students (name, email, password, aadhaar_number, university_id, program_id, enrollment_year, current_semester, cgpa, gender)
VALUES 
('Rahul Sharma', 'rahul@example.com', '$2y$10$9l8DxfQXUq4Et.e4BbwIcODSAy5ZMYWbLXzW2ZDvIx9sEjC3awKZm', '123456789012', 1, 1, 2022, 3, 8.5, 'male'),
('Priya Singh', 'priya@example.com', '$2y$10$9l8DxfQXUq4Et.e4BbwIcODSAy5ZMYWbLXzW2ZDvIx9sEjC3awKZm', '234567890123', 1, 2, 2022, 3, 9.2, 'female'),
('Amit Kumar', 'amit@example.com', '$2y$10$9l8DxfQXUq4Et.e4BbwIcODSAy5ZMYWbLXzW2ZDvIx9sEjC3awKZm', '345678901234', 2, 4, 2021, 5, 7.8, 'male'),
('Sneha Patel', 'sneha@example.com', '$2y$10$9l8DxfQXUq4Et.e4BbwIcODSAy5ZMYWbLXzW2ZDvIx9sEjC3awKZm', '456789012345', 3, 6, 2023, 1, 8.9, 'female'),
('Rajesh Gupta', 'rajesh@example.com', '$2y$10$9l8DxfQXUq4Et.e4BbwIcODSAy5ZMYWbLXzW2ZDvIx9sEjC3awKZm', '567890123456', 4, NULL, 2022, 3, 7.5, 'male');
-- Password: student123

-- Sample Academic Records
INSERT INTO academic_records (student_id, year, semester, course_id, course_name, credits, gpa, status)
VALUES 
(1, 2022, 1, 1, 'Introduction to Programming', 4, 8.5, 'passed'),
(1, 2022, 2, 2, 'Data Structures', 4, 9.0, 'passed'),
(2, 2022, 1, 3, 'Digital Electronics', 4, 9.2, 'passed'),
(3, 2021, 1, 5, 'Database Management Systems', 3, 7.5, 'passed'),
(3, 2021, 2, NULL, 'Operating Systems', 3, 8.0, 'passed'),
(4, 2023, 1, 7, 'Algorithms', 4, 8.9, 'passed');

-- Sample Scholarships
INSERT INTO scholarships (name, description, amount, student_id, academic_year, status)
VALUES 
('Merit Scholarship', 'Awarded for academic excellence', 50000.00, 1, '2022-2023', 'approved'),
('Need-Based Financial Aid', 'Provided based on financial need', 30000.00, 3, '2021-2022', 'disbursed'),
('Sports Scholarship', 'Awarded for excellence in sports', 25000.00, 4, '2023-2024', 'pending');

-- Sample Documents
INSERT INTO documents (student_id, title, document_type, file_path, file_size, file_type, approval_status)
VALUES 
(1, '10th Marksheet', 'certificate', 'dummy_path_1.pdf', 1024, 'application/pdf', 'approved'),
(1, '12th Marksheet', 'certificate', 'dummy_path_2.pdf', 1536, 'application/pdf', 'approved'),
(2, 'Aadhaar Card', 'identification', 'dummy_path_3.jpg', 512, 'image/jpeg', 'approved'),
(3, 'Semester 1 Marksheet', 'transcript', 'dummy_path_4.pdf', 768, 'application/pdf', 'pending'),
(4, 'Admission Letter', 'letter', 'dummy_path_5.pdf', 640, 'application/pdf', 'pending');

-- Sample Notices
INSERT INTO notices (university_id, title, content, notice_type, published_by)
VALUES 
(1, 'Semester Registration', 'Registration for the next semester will start from June 15, 2023', 'announcement', 1),
(2, 'Fee Payment Deadline', 'Last date for fee payment is July 31, 2023', 'deadline', 2),
(3, 'Annual Technical Fest', 'The annual technical fest will be held from September 10-12, 2023', 'event', 3);
