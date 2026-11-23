# Manual Database Implementation & Migration Guide

**Project:** WMSU Student Registration & Enrollment System  
**Target Engine:** MariaDB Server `11.8.9` (Kali Linux VM at `192.168.1.10`)  
**Client Environment:** WSL Ubuntu (`mariadb` client `11.8.6`)  
**Source Files:** `migrations/001_online_enrollment.sql`, `schema.sql`, `student_registration_backup_20261123.sql`

> [!CAUTION]
> **CRITICAL SAFETY WARNING BEFORE RUNNING `001_online_enrollment.sql`:**  
> Line 18 of `migrations/001_online_enrollment.sql` contains `USE student_registration;`.  
> If you pipe the unmodified file directly into `mysql`, **it will switch from `student_registration_dev` to your live production `student_registration` database!**  
> Always use the `sed` command shown in **Section C** below to rewrite `USE student_registration;` to `USE student_registration_dev;` during execution.

---

## A. Pre-Implementation Checklist

Before executing any SQL commands:

1. **Verify MariaDB Server Version (Kali VM):**
   Confirm the target server is MariaDB `10.5+` / `11.8.x` (required for `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS`, and `ADD CONSTRAINT IF NOT EXISTS`).
2. **Verify Existing Backup:**
   Confirm `C:\Users\Johan\student_registration_backup_20261123.sql` (or `/mnt/c/Users/Johan/student_registration_backup_20261123.sql` in WSL) exists, is non-empty (`18,512` bytes), and ends with `-- Dump completed`.
3. **Confirm Target Database Isolation:**
   Ensure you are connecting to `student_registration_dev` and **not** `student_registration`.
4. **Verify Required Permissions:**
   `dbadmin` currently only has privileges on `student_registration.*` and cannot create databases. An administrator must create `student_registration_dev` and grant privileges on `student_registration_dev.*`.

---

## B. Database Preparation

### 1. Administrator Commands (Run on Kali VM via `sudo mysql`)
An administrator on the Kali Linux VM must create the isolated development database and grant privileges. Choose one of the two privilege options below:

**Option 1: Use a dedicated dev account (Recommended — leaves `dbadmin` untouched):**
```sql
CREATE DATABASE IF NOT EXISTS student_registration_dev
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'devtester'@'%' IDENTIFIED BY 'devpass123';
GRANT ALL PRIVILEGES ON student_registration_dev.* TO 'devtester'@'%';
FLUSH PRIVILEGES;
```

**Option 2: Grant `dbadmin` access to `student_registration_dev`:**
```sql
CREATE DATABASE IF NOT EXISTS student_registration_dev
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON student_registration_dev.* TO 'dbadmin'@'%';
FLUSH PRIVILEGES;
```

### 2. Import Existing Backup into `student_registration_dev` (Run from WSL)
Once the administrator has created `student_registration_dev` and granted access (replace `<USER>` with `devtester` or `dbadmin`):

```bash
sed 's/`student_registration`/`student_registration_dev`/g' /mnt/c/Users/Johan/student_registration_backup_20261123.sql \
  | mysql -h 192.168.1.10 -u <USER> -p student_registration_dev
```

*(Using `sed` ensures any database-qualified statements in the dump target `student_registration_dev` only.)*

---

## C. Migration Execution (`001_online_enrollment.sql`)

> [!IMPORTANT]
> **Fresh Installation vs. Existing Database:**
> - Use `schema.sql` **only** for a brand-new, empty database.
> - Use `migrations/001_online_enrollment.sql` for an **existing** database (including `student_registration_dev` loaded from your backup).

### Execute Migration Against `student_registration_dev` (from WSL)
Run the migration while rewriting `USE student_registration;` to `USE student_registration_dev;` so it never touches production:

```bash
sed 's/^USE student_registration;/USE student_registration_dev;/' /var/www/html/migrations/001_online_enrollment.sql \
  | mysql -h 192.168.1.10 -u <USER> -p --show-warnings student_registration_dev
```

### Checking for Errors
- If the command exits with status `0` and prints no `ERROR` lines, all `ALTER TABLE`, `CREATE TABLE IF NOT EXISTS`, and `INSERT IGNORE` statements succeeded.
- Any `Note` warnings about `"Column ... already exists"` or `"Duplicate entry ..."` on `INSERT IGNORE` are expected on reruns, **not** errors.
- Any `ERROR 1062` (duplicate key) or `ERROR 1452` (foreign key failure) means existing data violated a new constraint—see **Section G (Potential Schema Problems)** below.

---

## D. Verification Queries

Connect to `student_registration_dev` from WSL:
```bash
mysql -h 192.168.1.10 -u <USER> -p student_registration_dev
```

Run the following verification queries inside the MariaDB prompt:

### 1. Verify All 12 Expected Tables Exist
```sql
SHOW TABLES;
```
Expected: `activity_logs`, `application_guardians`, `application_previous_schools`, `application_status_history`, `applications`, `courses`, `documents`, `notifications`, `registration_requirements`, `school_years`, `students`, `users`.

### 2. Verify Modified Columns, Defaults, and ENUM Values
```sql
SHOW COLUMNS FROM users;
SHOW COLUMNS FROM students;
SHOW COLUMNS FROM applications;
SHOW COLUMNS FROM documents;
```
Check:
- `users.role` is `enum('admin','registrar','student')`, `NOT NULL`, default `'student'`.
- `users.email` is `varchar(100)`, `YES`, `UNI`.
- `users.is_active` is `tinyint(1)`, `NO`, default `1`.
- `users.student_id` is `int(11)`, `YES`, `MUL`.

### 3. Verify Indexes and Unique Constraints
```sql
SHOW INDEX FROM users;
SHOW INDEX FROM students;
SHOW INDEX FROM applications;
SHOW INDEX FROM documents;
```
Check for `uq_users_email`, `idx_users_role`, `idx_users_student_id`, `uq_students_student_no`, `idx_students_course_id`, `idx_students_status_campus`, `idx_applications_user_id`, `idx_applications_status`, `idx_applications_term`, `idx_documents_application`, `idx_documents_status`.

### 4. Verify Foreign Keys
```sql
SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'student_registration_dev'
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
```

### 5. Verify Seed Data & Preserved Existing Records
```sql
SELECT id, username, role, is_active FROM users;
SELECT id, code, name FROM courses;
SELECT COUNT(*) AS student_count FROM students;
SELECT COUNT(*) AS log_count FROM activity_logs;
SELECT * FROM school_years;
SELECT code, name, applies_to, is_required FROM registration_requirements;
```

---

## E. Rerun Testing (Idempotency)

To verify `001_online_enrollment.sql` is safely rerunnable:

1. Record row counts before the second run:
   ```sql
   SELECT
     (SELECT COUNT(*) FROM users) AS users_cnt,
     (SELECT COUNT(*) FROM students) AS students_cnt,
     (SELECT COUNT(*) FROM courses) AS courses_cnt,
     (SELECT COUNT(*) FROM school_years) AS sy_cnt,
     (SELECT COUNT(*) FROM registration_requirements) AS req_cnt;
   ```
2. Run the exact same `sed ... | mysql ... student_registration_dev` command from **Section C** a second time.
3. Re-run the row-count query above and `SHOW INDEX FROM users;`.
4. **What to check carefully:**
   - `MODIFY COLUMN role ENUM(...)` and `ADD ... IF NOT EXISTS` complete without `ERROR`.
   - `INSERT IGNORE INTO school_years` and `INSERT IGNORE INTO registration_requirements` do **not** create duplicate rows (counts stay identical).
   - No duplicate indexes are created.

---

## F. Rollback and Recovery (Development Database Only)

> [!WARNING]
> **Never restore a backup over the live production `student_registration` database during dev testing.**

If the migration fails halfway in `student_registration_dev`:

1. Drop and recreate `student_registration_dev` (requires admin or `DROP` privilege on `student_registration_dev`):
   ```sql
   DROP DATABASE IF EXISTS student_registration_dev;
   CREATE DATABASE student_registration_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Re-import the untouched backup:
   ```bash
   sed 's/`student_registration`/`student_registration_dev`/g' /mnt/c/Users/Johan/student_registration_backup_20261123.sql \
     | mysql -h 192.168.1.10 -u <USER> -p student_registration_dev
   ```
3. Fix the blocking data or SQL issue, then re-run Section C.

---

## G. Identified Potential Schema Problems & Risks

1. **Hardcoded `USE student_registration;` in `001_online_enrollment.sql` (High Risk):**
   Running the file without rewriting line 18 will target the live production database instead of `student_registration_dev`.
2. **`students.student_no` Unique Index (`uq_students_student_no`):**
   MariaDB allows multiple `NULL` values in a `UNIQUE` index, **but rejects duplicate non-NULL values or multiple empty strings `''`**. Any legacy row with `student_no = ''` must be updated to `NULL` or a unique value before adding `uq_students_student_no`.
3. **`students.course_id` Foreign Key (`fk_students_course`):**
   If any existing row in `students` has a `course_id` that does not exist in `courses.id`, adding `fk_students_course` will fail with `ERROR 1452`.
4. **`users.role` Default Changed to `'student'`:**
   `MODIFY COLUMN role ENUM('admin', 'registrar', 'student') NOT NULL DEFAULT 'student'` preserves existing `'admin'` and `'registrar'` rows, but any future `INSERT INTO users` that omits `role` will default to `'student'` instead of `'registrar'`.
5. **Returning Student Applications & Duplicate Prevention:**
   `applications.student_no` is intentionally non-unique (a returning student may apply across multiple terms), whereas `students.student_no` and `students.email` are `UNIQUE`. Phase 4 approval logic must check for an existing `students` row inside a transaction and link `applications.approved_student_id` and `users.student_id` rather than inserting a duplicate `students` row.
6. **`documents` Cascade Deletion vs. Physical Files:**
   `fk_documents_application` and `fk_documents_uploader` use `ON DELETE CASCADE`. Deleting an application or user at the SQL level removes the `documents` row without deleting the physical file outside the web root; application-level delete handlers must remove files explicitly.
7. **Schema Equivalence (`schema.sql` vs. Migrated DB):**
   In `schema.sql`, `students.student_no` uses an inline `UNIQUE` constraint (named `student_no` by MariaDB), whereas `001_online_enrollment.sql` names it `uq_students_student_no`. Functionally both enforce uniqueness on `student_no`.
