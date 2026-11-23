# Database Verification Checklist (`student_registration_dev`)

Use this checklist to verify your manual migration of `migrations/001_online_enrollment.sql` against `student_registration_dev`.

---

## 1. Pre-Flight & Preparation
- [ ] Confirmed MariaDB version on Kali VM is `11.8.9` (`SELECT VERSION();`).
- [ ] Confirmed backup file `student_registration_backup_20261123.sql` exists (`18,512` bytes) and is untouched.
- [ ] Administrator created `student_registration_dev` with `utf8mb4_unicode_ci`.
- [ ] Administrator granted privileges on `student_registration_dev.*` (to `devtester` or `dbadmin`).
- [ ] Imported `student_registration_backup_20261123.sql` into `student_registration_dev` only.

---

## 2. Migration Execution & Rerunnability
- [ ] Executed `001_online_enrollment.sql` against `student_registration_dev` (with `USE student_registration;` rewritten to `USE student_registration_dev;`).
- [ ] First execution completed with zero `ERROR` messages.
- [ ] Executed `001_online_enrollment.sql` a **second time** against `student_registration_dev`.
- [ ] Second execution completed with zero `ERROR` messages and no duplicate seed rows.

---

## 3. Table & Column Verification
Run `SHOW TABLES;` in `student_registration_dev`:
- [ ] `courses` exists (existing)
- [ ] `students` exists (existing)
- [ ] `users` exists (existing)
- [ ] `activity_logs` exists (existing)
- [ ] `school_years` exists (new)
- [ ] `registration_requirements` exists (new)
- [ ] `applications` exists (new)
- [ ] `application_guardians` exists (new)
- [ ] `application_previous_schools` exists (new)
- [ ] `documents` exists (new)
- [ ] `application_status_history` exists (new)
- [ ] `notifications` exists (new)

Run `SHOW COLUMNS FROM users;` and `SHOW COLUMNS FROM students;`:
- [ ] `users.role` is `enum('admin','registrar','student')` with default `'student'`.
- [ ] `users.email` is `varchar(100)`, `NULL` allowed, `Key = UNI`.
- [ ] `users.is_active` is `tinyint(1)`, `NOT NULL`, default `1`.
- [ ] `users.student_id` is `int(11)`, `NULL` allowed, `Key = MUL`.
- [ ] `students.student_no` has `Key = UNI` (`uq_students_student_no`).
- [ ] `students.course_id` has `Key = MUL` (`idx_students_course_id`).

---

## 4. Foreign Keys & Indexes Verification
Run the `information_schema.KEY_COLUMN_USAGE` query from the Implementation Guide:
- [ ] `users.fk_users_student` → `students(id)` (`ON DELETE SET NULL`)
- [ ] `students.fk_students_course` → `courses(id)` (`ON DELETE SET NULL`)
- [ ] `applications.fk_applications_user` → `users(id)` (`ON DELETE CASCADE`)
- [ ] `applications.fk_applications_course` → `courses(id)` (`ON DELETE SET NULL`)
- [ ] `applications.fk_applications_reviewer` → `users(id)` (`ON DELETE SET NULL`)
- [ ] `applications.fk_applications_student` → `students(id)` (`ON DELETE SET NULL`)
- [ ] `application_guardians.fk_guardians_application` → `applications(id)` (`ON DELETE CASCADE`)
- [ ] `application_previous_schools.fk_prev_schools_application` → `applications(id)` (`ON DELETE CASCADE`)
- [ ] `documents.fk_documents_application` → `applications(id)` (`ON DELETE CASCADE`)
- [ ] `documents.fk_documents_requirement` → `registration_requirements(id)` (`ON DELETE SET NULL`)
- [ ] `documents.fk_documents_uploader` → `users(id)` (`ON DELETE CASCADE`)
- [ ] `documents.fk_documents_verifier` → `users(id)` (`ON DELETE SET NULL`)
- [ ] `application_status_history.fk_history_application` → `applications(id)` (`ON DELETE CASCADE`)
- [ ] `application_status_history.fk_history_user` → `users(id)` (`ON DELETE SET NULL`)
- [ ] `notifications.fk_notifications_user` → `users(id)` (`ON DELETE CASCADE`)
- [ ] `notifications.fk_notifications_application` → `applications(id)` (`ON DELETE CASCADE`)

---

## 5. Data Preservation & Seed Data Verification
- [ ] Existing `admin` / `registrar` accounts in `users` retained their original `role` and `password_hash`.
- [ ] Existing `courses` rows (`BSCS`, `BSIT`, `BSCE`, `BSED`, `BSN`) are intact.
- [ ] Existing `students` count matches pre-migration backup count.
- [ ] Existing `activity_logs` count matches pre-migration backup count.
- [ ] `school_years` has 1 seeded active row (`2026-2027`, `1st Semester`, `is_active = 1`).
- [ ] `registration_requirements` has 6 seeded rows (`FORM_138`, `PSA_BIRTH`, `GOOD_MORAL`, `TOR`, `CLEARANCE`, `ID_PHOTO`).

---

## 6. Post-Test Cleanup (Optional)
- [ ] `student_registration` (production) was never touched during testing.
- [ ] Backup file `student_registration_backup_20261123.sql` remains intact.
