# WMSU Student Registration System

A decoupled, multi-tier CRUD (Create, Read, Update, Delete) web application tailored for the **Western Mindanao State University (WMSU)** Office of the University Registrar in **Zamboanga City**. Built as an Operating Systems course project demonstrating **service isolation**, **network bridging**, and **inter-process communication (IPC)** across a virtualized environment.

---

## 🏗️ Architecture & Network Topology

The system is split across two isolated environments communicating over a TCP/IP network bridge:
* **Frontend & Application Tier (Web Server):** WSL Ubuntu running Apache2 and PHP 8+, handling sessions, CSRF protection, validation, and rendering the WMSU Registrar UI.
* **Database Tier (Database Server):** VirtualBox Kali Linux VM running MariaDB, storing relational student, course, user, and audit log records.
* **Communication:** Connected via VirtualBox **Bridged Networking**, allowing WSL to route PDO prepared queries across the virtual network interface to Port `3306`.

---

## 🛠️ Tech Stack & Project Structure

* **Web Server:** Apache2 (WSL Ubuntu)
* **Application Language:** Native PHP 8+ (PDO prepared statements, sessions, `password_hash()` / `password_verify()`, CSRF tokens)
* **Database:** MariaDB (Kali Linux VM)
* **Styling:** Pure Vanilla CSS (`style.css` with WMSU crimson CSS custom properties)

```text
/var/www/html/
│
├── db_config.php          # Centralized PDO database connection handler (WSL -> Kali VM)
├── schema.sql             # Fresh-install database schema + WMSU seed data
├── migrations/
│   └── 001_online_enrollment.sql # Additive migration for existing databases
├── includes/
│   ├── auth.php           # Session, RBAC guards, CSRF, flash messages, query/status helpers, audit logging
│   ├── documents_helper.php # Secure file upload validation (finfo MIME, 5MB limit, private storage)
│   └── layout.php         # Role-aware WMSU top navigation bar, unread badge, flash banner, and footer
├── login.php              # Multi-role sign-in page (with rate limiting & audit logging)
├── logout.php             # Session termination handler
├── register_account.php   # Public student portal account registration
├── download_document.php  # Authorized document preview/download endpoint
├── notifications.php      # In-system notification center
├── reports.php            # Enrollment & application summary reports (CSV + print)
├── student/
│   ├── dashboard.php      # Student portal dashboard & application list
│   ├── apply.php          # 6-step online registration form (Draft & Submit)
│   ├── requirements.php   # Document upload & verification status tracker
│   ├── track.php          # Application status timeline & registrar remarks
│   └── profile.php        # Student profile & password management
├── registrar/
│   ├── dashboard.php      # Registrar metrics & recent applications queue
│   ├── applications.php   # Searchable/filterable online applications queue
│   └── review_application.php # Application review, document verification, & transactional approval
├── admin/
│   ├── users.php          # Staff/student user management & activation (Admin only)
│   └── settings.php       # School years & registration requirements config (Admin only)
├── index.php              # Direct student registration form & summary stats (Registrar/Admin)
├── view_students.php      # Official student directory with search, filters, badges, & print view
├── export_students.php    # Filtered CSV download endpoint for student records
├── edit_student.php       # Official student update interface
├── delete_student.php     # CSRF-protected POST deletion handler
├── courses.php            # WMSU degree program management (add, edit, guarded delete)
├── logs.php               # Audit log viewer with action-type filter
├── dummy.sql              # Sample 5-student WMSU seed script
├── DATABASE_IMPLEMENTATION_GUIDE.md # Step-by-step manual DB migration guide
├── DATABASE_VERIFICATION_CHECKLIST.md # Manual DB verification checklist
├── PROJECT_PROGRESS_REPORT.md # Completed phases & remaining live-deployment steps
├── archive/
│   └── index.legacy.html  # Archived legacy static template
├── style.css              # WMSU crimson academic registrar stylesheet (+ print & wizard)
├── .gitignore             # Git exclusion rules
└── README.md              # Project documentation & setup guide
```

---

## 🗄️ Database Schema & Migration Guide

### Fresh Installation (`schema.sql`)
Use [schema.sql](schema.sql) **only for fresh database installations** on MariaDB 11.8+:
```bash
sudo mysql < schema.sql
```

### Upgrading an Existing Database (`migrations/001_online_enrollment.sql`)
Use [migrations/001_online_enrollment.sql](migrations/001_online_enrollment.sql) for existing `student_registration` databases. It is **additive and rerunnable** and preserves all existing `users`, `students`, `courses`, and `activity_logs` rows.

#### Prerequisites
1. Verify MariaDB 10.5+ / 11.8+ is running on the Kali VM.
2. **Back up the live database** before running any migration:
   ```bash
   mysqldump -h 192.168.1.10 -u dbadmin -p student_registration > backup_before_001.sql
   ```

#### Expected Changes
* **Modified `users`:** Expands `role` to `ENUM('admin', 'registrar', 'student')`, adds `email` (`UNIQUE`), `is_active`, `student_id` (FK to `students.id`), and role/student indexes.
* **Hardened `students`:** Adds `uq_students_student_no`, `idx_students_course_id`, `idx_students_status_campus`, and `fk_students_course` if missing.
* **New Tables:** `school_years`, `registration_requirements`, `applications`, `application_guardians`, `application_previous_schools`, `documents`, `application_status_history`, and `notifications`.
* **Seeded Defaults:** Active term `2026-2027` (`1st Semester`) and 6 WMSU registration requirements (`FORM_138`, `PSA_BIRTH`, `GOOD_MORAL`, `TOR`, `CLEARANCE`, `ID_PHOTO`).

#### Rollback Considerations
* If you need to roll back after running `001_online_enrollment.sql`, restore from your `mysqldump` backup (`mysql ... student_registration < backup_before_001.sql`) rather than manually dropping columns, so foreign keys and `users.role` enum values return cleanly to their prior state.

| Table | Purpose | Key Columns |
| :--- | :--- | :--- |
| `courses` | WMSU degree programs | `id`, `code`, `name`, `department`, `created_at` |
| `users` | Admin, registrar & student accounts | `id`, `username`, `full_name`, `email`, `password_hash`, `role`, `is_active`, `student_id`, `created_at` |
| `students` | Official enrolled student records | `id`, `student_no`, `full_name`, `email`, `course`, `course_id`, `year_level`, `status`, `campus`, `school_year`, `semester`, `registration_date`, `updated_at` |
| `activity_logs` | Audit trail for auth & CRUD | `id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `created_at` |
| `school_years` | Active academic term settings | `id`, `label`, `semester`, `is_active`, `registration_open` |
| `registration_requirements` | Configurable required documents | `id`, `code`, `name`, `applies_to`, `is_required`, `is_active` |
| `applications` | Online registration applications | `id`, `reference_no`, `user_id`, `classification`, `student_no`, `course_id`, `status`, `approved_student_id` |
| `application_guardians` | Applicant guardian details | `id`, `application_id`, `full_name`, `relationship`, `contact_no`, `address` |
| `application_previous_schools` | Applicant prior school history | `id`, `application_id`, `school_name`, `last_grade_or_year`, `gwa` |
| `documents` | Uploaded requirement metadata | `id`, `application_id`, `requirement_id`, `uploaded_by`, `stored_filename`, `verification_status` |
| `application_status_history` | Application review timeline | `id`, `application_id`, `old_status`, `new_status`, `changed_by`, `remarks` |
| `notifications` | In-system applicant notifications | `id`, `user_id`, `application_id`, `title`, `message`, `is_read` |

### Default Seed Data
* **WMSU Programs:** `BSCS` (College of Computing Studies), `BSIT` (College of Computing Studies), `BSCE` (College of Engineering), `BSED` (College of Teacher Education), `BSN` (College of Nursing).
* **Campuses:** `Main - Baliwasan`, `San Ramon`, `Curuan`.
* **Default Login:** Username `admin`, Password `admin123` (hashed with `password_hash()`). Change this password after initial setup.

---

## 🔐 Authentication & Access Control

* `login.php` authenticates against `users` using `password_verify()`, regenerates the session ID (`session_regenerate_id(true)`), logs `LOGIN_SUCCESS` / `LOGIN_FAILED` events, and applies session-based rate limiting (5 failed attempts triggers a 60-second cooldown).
* `logout.php` logs the sign-out event, clears the session cookie, and redirects to `login.php`.
* **Protected Routes by Role:**
  * **Student (`student/*`, `notifications.php`, `download_document.php`):** Can register an account (`register_account.php`), manage their own profile, submit/revise 6-step applications, upload requirements, track status history, and view their own documents/notifications. Cannot access registrar/admin pages or other students' data.
  * **Registrar (`registrar/*`, `index.php`, `view_students.php`, `edit_student.php`, `delete_student.php`, `courses.php`, `export_students.php`, `reports.php`, `logs.php`, `download_document.php`):** Manages online applications, verifies/rejects documents, transactionally approves/enrolls students, manages courses/records, and views reports/audit logs.
  * **Admin (`admin/users.php`, `admin/settings.php` + all Registrar routes):** Manages staff/student accounts, active school years, and configurable registration requirements.
* **Private Document Storage:** Uploaded requirements are stored outside the public web root at `/var/www/wmsu_uploads` (configurable via `UPLOAD_DIR` env var) and served only through `download_document.php` after ownership/role checks.

---

## 🚀 Step-by-Step Launch & Execution Guide

### Step 1: Start and Verify the Database Server (VirtualBox Kali VM)
1. Start your **Kali Linux VM** in VirtualBox with **Bridged Adapter** networking.
2. Find the VM's current IP address with `ip a` and ensure MariaDB is running (`sudo systemctl restart mariadb`).
3. Apply `schema.sql` on the Kali VM:
   ```bash
   sudo mysql < schema.sql
   ```

### Step 2: Update `db_config.php` and Sync to WSL Apache
1. Update `$host` in `db_config.php` to match your Kali VM's IP address (or set the `DB_HOST` environment variable in Apache/PHP to override it without editing `db_config.php`).
2. From your Windows project folder, sync files to `/var/www/html` in WSL:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\sync-to-wsl.ps1
   ```

### Step 3: Start Apache and Launch the Web App
1. In WSL Ubuntu, ensure Apache is running:
   ```bash
   sudo systemctl start apache2
   ```
2. Open your browser:
   * **Sign In:** `http://localhost/login.php`
   * **Register Student:** `http://localhost/index.php`
   * **Student Directory / Print Roster:** `http://localhost/view_students.php`
   * **Courses:** `http://localhost/courses.php`
   * **Audit Logs:** `http://localhost/logs.php`

### Step 4: Sample Data & Reset Workflow (`dummy.sql`)
To load or reset the 5 sample WMSU student records from WSL (replace `192.168.1.10` with your Kali VM's IP):
```bash
# Optional: clear existing student rows before re-seeding
mysql -h 192.168.1.10 -u dbadmin -p -D student_registration -e "DELETE FROM students;"

# Insert the 5 sample WMSU students
mysql -h 192.168.1.10 -u dbadmin -p < /var/www/html/dummy.sql
```
