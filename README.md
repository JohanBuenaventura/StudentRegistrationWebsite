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
├── schema.sql             # Database schema (courses, users, students, activity_logs) + WMSU seed data
├── includes/
│   ├── auth.php           # Session, auth guard, CSRF, flash messages, query/term helpers, activity logging
│   └── layout.php         # Shared WMSU top navigation bar, flash banner, and page shell
├── login.php              # Registrar/admin sign-in page
├── logout.php             # Session termination handler
├── index.php              # Student registration form, validation, duplicate checks, and quick stats (Protected)
├── view_students.php      # Student directory with search, program/status/campus filters, badges, and CSV export
├── export_students.php    # Filtered CSV download endpoint for student records (Protected)
├── edit_student.php       # Student update interface and activity logging (Protected)
├── delete_student.php     # CSRF-protected POST deletion handler with activity logging (Protected)
├── courses.php            # WMSU degree program management (add, edit, guarded delete) (Protected)
├── logs.php               # Audit log viewer with action-type filter (Protected)
├── dummy.sql              # Sample 5-student WMSU seed script
├── test_db.php            # Standalone DB connectivity check
├── style.css              # WMSU crimson academic registrar stylesheet
├── .gitignore             # Git exclusion rules
└── README.md              # Project documentation & setup guide
```

---

## 🗄️ Database Schema (`schema.sql`)

Execute [schema.sql](schema.sql) on your Kali Linux VM inside `sudo mysql` (or `sudo mysql < schema.sql`). It creates and seeds four tables inside `student_registration`:

| Table | Purpose | Key Columns |
| :--- | :--- | :--- |
| `courses` | WMSU degree programs | `id`, `code`, `name`, `department`, `created_at` |
| `users` | Registrar/admin accounts | `id`, `username`, `full_name`, `password_hash`, `role`, `created_at` |
| `students` | Enrolled student records | `id`, `student_no`, `full_name`, `email`, `course`, `course_id`, `year_level`, `status`, `campus`, `school_year`, `semester`, `registration_date`, `updated_at` |
| `activity_logs` | Audit trail for auth & CRUD | `id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `created_at` |

### Default Seed Data
* **WMSU Programs:** `BSCS` (College of Computing Studies), `BSIT` (College of Computing Studies), `BSCE` (College of Engineering), `BSED` (College of Teacher Education), `BSN` (College of Nursing).
* **Campuses:** `Main - Baliwasan`, `San Ramon`, `Curuan`.
* **Default Login:** Username `admin`, Password `admin123` (hashed with `password_hash()`). Change this password after initial setup.

---

## 🔐 Authentication & Access Control

* `login.php` authenticates against `users` using `password_verify()` and regenerates the session ID (`session_regenerate_id(true)`).
* `login.php` authenticates against `users` using `password_verify()`, regenerates the session ID (`session_regenerate_id(true)`), logs `LOGIN_SUCCESS` / `LOGIN_FAILED` events, and applies session-based rate limiting (5 failed attempts triggers a 60-second cooldown).
* `logout.php` logs the sign-out event, clears the session cookie, and redirects to `login.php`.
* **Protected Routes:** `index.php` (Create), `edit_student.php` (Update), `delete_student.php` (POST + CSRF Delete), `courses.php` (Course management), `export_students.php` (CSV export), and `logs.php` (Audit Logs) require an active session and redirect unauthenticated visitors to `login.php`.
* `view_students.php` lets visitors search and filter by program, status, and campus, supports clean roster printing (`@media print` in `style.css` hides navbars, filters, and action buttons), and exposes **Export CSV**, **Edit**, and **Delete** controls when signed in.

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
