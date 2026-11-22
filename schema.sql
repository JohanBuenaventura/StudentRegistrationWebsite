-- Western Mindanao State University (WMSU) - Zamboanga City
-- Student Registration System Schema (MariaDB)
-- Run this on your Kali Linux VM inside `sudo mysql`

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
) ENGINE=InnoDB;

-- 2. Users table (Registrar / Admin authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'registrar') NOT NULL DEFAULT 'registrar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_no VARCHAR(30) NOT NULL UNIQUE,
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
    CONSTRAINT fk_students_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 4. Activity logs table (Audit trail)
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
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Safe upgrade helper if you already have an existing `students` table:
-- (MariaDB 10.2+ supports ADD COLUMN IF NOT EXISTS)
-- ---------------------------------------------------------------------------
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS student_no VARCHAR(30) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS course_id INT NULL AFTER course,
    ADD COLUMN IF NOT EXISTS year_level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER course_id,
    ADD COLUMN IF NOT EXISTS status ENUM('Active', 'Pending', 'Inactive', 'Graduated') NOT NULL DEFAULT 'Active' AFTER year_level,
    ADD COLUMN IF NOT EXISTS campus ENUM('Main - Baliwasan', 'San Ramon', 'Curuan') NOT NULL DEFAULT 'Main - Baliwasan' AFTER status,
    ADD COLUMN IF NOT EXISTS school_year VARCHAR(20) NOT NULL DEFAULT '2026-2027' AFTER campus,
    ADD COLUMN IF NOT EXISTS semester ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL DEFAULT '1st Semester' AFTER school_year,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER registration_date;

-- Backfill student_no for any legacy rows that lack one
UPDATE students
SET student_no = CONCAT('2026-', LPAD(id, 4, '0'))
WHERE student_no IS NULL OR student_no = '';

-- ---------------------------------------------------------------------------
-- Seed Data (WMSU Programs & Default Registrar Account)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO courses (code, name, department) VALUES
    ('BSCS', 'BS Computer Science', 'College of Computing Studies'),
    ('BSIT', 'BS Information Technology', 'College of Computing Studies'),
    ('BSCE', 'BS Civil Engineering', 'College of Engineering'),
    ('BSED', 'BS Secondary Education', 'College of Teacher Education'),
    ('BSN', 'BS Nursing', 'College of Nursing');

-- Default registrar admin account (username: admin / password: admin123)
-- Change this password after first login in production.
INSERT IGNORE INTO users (username, full_name, password_hash, role) VALUES
    ('admin', 'WMSU Registrar Admin', '$2y$10$63Jwvd7YGb2Wjsr/LU1kiO/WYZPTBWdwQXGxtWP8AiCbENxmyaTm6', 'admin');
