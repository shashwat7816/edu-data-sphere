
-- Update university_staff table description and comments
ALTER TABLE university_staff MODIFY COLUMN designation VARCHAR(100) COMMENT 'Government Staff Designation';

-- Update comment to reflect government staff context
ALTER TABLE university_staff COMMENT = 'Government Staff Information for Educational Institutions';
