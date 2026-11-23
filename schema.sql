-- Western Mindanao State University (WMSU) - Zamboanga City
-- Fresh Installation Schema (MariaDB 11.8+)
-- Use this file ONLY for fresh database installations.
-- For existing databases, run: migrations/001_online_enrollment.sql

CREATE DATABASE IF NOT EXISTS student_registration
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_registration;

-- 1. Courses / Programs table
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL DEFAULT 'College of Computing Studies',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Official Enrolled Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_no VARCHAR(30) NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    course VARCHAR(100) NOT NULL,
    course_id INT NULL,
    year_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('Active', 'Pending', 'Inactive', 'Graduated') NOT NULL DEFAULT 'Active',
    campus ENUM('Main - Baliwasan', 'San Ramon', 'Curuan') NOT NULL DEFAULT 'Main - Baliwasan',
    school_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
    semester ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL DEFAULT '1st Semester',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_students_course_id (course_id),
    INDEX idx_students_status_campus (status, campus),
    CONSTRAINT fk_students_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Users table (Admin, Registrar, and Student portal accounts)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'registrar', 'student') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    student_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_student_id (student_id),
    CONSTRAINT fk_users_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Activity logs table (System-wide audit trail)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id INT NULL,
    details VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. School Years / Active Enrollment Terms
CREATE TABLE IF NOT EXISTS school_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL UNIQUE,
    semester ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL DEFAULT '1st Semester',
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    registration_open TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Configurable Registration Requirements
CREATE TABLE IF NOT EXISTS registration_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    applies_to ENUM('All', 'New', 'Returning', 'Transferee') NOT NULL DEFAULT 'All',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Online Applications
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    classification ENUM('New', 'Returning', 'Transferee') NOT NULL DEFAULT 'New',
    student_no VARCHAR(30) NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    sex ENUM('Male', 'Female', 'Prefer not to say') NULL,
    contact_no VARCHAR(30) NULL,
    email VARCHAR(100) NOT NULL,
    address VARCHAR(255) NULL,
    school_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
    semester ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL DEFAULT '1st Semester',
    campus ENUM('Main - Baliwasan', 'San Ramon', 'Curuan') NOT NULL DEFAULT 'Main - Baliwasan',
    course_id INT NULL,
    year_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('Draft', 'Submitted', 'Under Review', 'Requires Correction', 'Approved', 'Rejected') NOT NULL DEFAULT 'Draft',
    registrar_remarks TEXT NULL,
    internal_notes TEXT NULL,
    submitted_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    approved_student_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_applications_user_id (user_id),
    INDEX idx_applications_status (status),
    INDEX idx_applications_term (school_year, semester, campus),
    CONSTRAINT fk_applications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_applications_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_applications_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_applications_student
        FOREIGN KEY (approved_student_id) REFERENCES students(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Application Guardian Information
CREATE TABLE IF NOT EXISTS application_guardians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    contact_no VARCHAR(30) NOT NULL,
    address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_guardians_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Application Previous School Information
CREATE TABLE IF NOT EXISTS application_previous_schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    school_name VARCHAR(150) NOT NULL,
    last_grade_or_year VARCHAR(50) NULL,
    academic_track_or_program VARCHAR(120) NULL,
    year_graduated VARCHAR(10) NULL,
    gwa DECIMAL(5,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prev_schools_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Uploaded Documents & Verification Status
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    requirement_id INT NULL,
    uploaded_by INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    verification_status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
    rejection_remarks VARCHAR(255) NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_documents_application (application_id),
    INDEX idx_documents_status (verification_status),
    CONSTRAINT fk_documents_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_documents_requirement
        FOREIGN KEY (requirement_id) REFERENCES registration_requirements(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_documents_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_documents_verifier
        FOREIGN KEY (verified_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Application Status Audit History
CREATE TABLE IF NOT EXISTS application_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by INT NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_application (application_id, created_at),
    CONSTRAINT fk_history_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. In-System Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_id INT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Seed Data (WMSU Programs, Default Admin, Active Term & Requirements)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO courses (code, name, department) VALUES
    ('BSCS', 'BS Computer Science', 'College of Computing Studies'),
    ('BSIT', 'BS Information Technology', 'College of Computing Studies'),
    ('BSCE', 'BS Civil Engineering', 'College of Engineering'),
    ('BSED', 'BS Secondary Education', 'College of Teacher Education'),
    ('BSN', 'BS Nursing', 'College of Nursing');

-- Default registrar admin account (username: admin / password: admin123)
INSERT IGNORE INTO users (username, full_name, password_hash, role) VALUES
    ('admin', 'WMSU Registrar Admin', '$2y$10$63Jwvd7YGb2Wjsr/LU1kiO/WYZPTBWdwQXGxtWP8AiCbENxmyaTm6', 'admin');

INSERT IGNORE INTO school_years (label, semester, is_active, registration_open) VALUES
    ('2026-2027', '1st Semester', 1, 1);

INSERT IGNORE INTO registration_requirements (code, name, description, applies_to, is_required, is_active) VALUES
    ('FORM_138', 'Form 138 / Report Card', 'Senior High School report card for incoming freshmen.', 'New', 1, 1),
    ('PSA_BIRTH', 'PSA Birth Certificate', 'Photocopy or scanned copy of PSA-issued birth certificate.', 'All', 1, 1),
    ('GOOD_MORAL', 'Certificate of Good Moral Character', 'Issued by previous school guidance office or principal.', 'New', 1, 1),
    ('TOR', 'Transcript of Records / Honorable Dismissal', 'Official academic transcript for transferees.', 'Transferee', 1, 1),
    ('CLEARANCE', 'Registrar / Accounting Clearance', 'Clearance slip for returning WMSU students.', 'Returning', 1, 1),
    ('ID_PHOTO', '2x2 ID Picture', 'Recent formal photo with white background.', 'All', 1, 1);
