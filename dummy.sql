-- Sample WMSU Student Records (5 dummy students)
-- Run on your Kali Linux VM inside `sudo mysql`:
--   sudo mysql < dummy.sql

USE student_registration;

INSERT IGNORE INTO students (student_no, full_name, email, course, course_id, year_level, status, campus) VALUES
('2026-0001', 'Maria Clara Santos', 'mc.santos@wmsu.edu.ph', 'BS Computer Science',
    (SELECT id FROM courses WHERE code = 'BSCS' LIMIT 1), 3, 'Active', 'Main - Baliwasan'),
('2026-0002', 'Rafael Miguel Reyes', 'rm.reyes@wmsu.edu.ph', 'BS Information Technology',
    (SELECT id FROM courses WHERE code = 'BSIT' LIMIT 1), 2, 'Active', 'Main - Baliwasan'),
('2026-0003', 'Amina Nur Hassan', 'an.hassan@wmsu.edu.ph', 'BS Nursing',
    (SELECT id FROM courses WHERE code = 'BSN' LIMIT 1), 4, 'Pending', 'San Ramon'),
('2026-0004', 'Carlo Antonio Bautista', 'ca.bautista@wmsu.edu.ph', 'BS Civil Engineering',
    (SELECT id FROM courses WHERE code = 'BSCE' LIMIT 1), 1, 'Active', 'Main - Baliwasan'),
('2026-0005', 'Clarisse Joy Mendoza', 'cj.mendoza@wmsu.edu.ph', 'BS Secondary Education',
    (SELECT id FROM courses WHERE code = 'BSED' LIMIT 1), 4, 'Graduated', 'Curuan');
