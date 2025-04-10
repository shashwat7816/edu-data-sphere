
-- Update university_staff table description and comments
ALTER TABLE university_staff MODIFY COLUMN designation VARCHAR(100) COMMENT 'Government Staff Designation';

-- Update comment to reflect government staff context
ALTER TABLE university_staff COMMENT = 'Government Staff Information for Educational Institutions';

-- Create notices table if not exists
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    notice_type ENUM('announcement', 'deadline', 'event', 'academic') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
) COMMENT = 'University notices and announcements';

-- Create login_logs table if not exists
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL, -- 'admin', 'staff', 'student'
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT
) COMMENT = 'User login history';

-- Add approval_status to documents table if not exists
ALTER TABLE documents 
ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS approval_date TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS approved_by INT NULL;
