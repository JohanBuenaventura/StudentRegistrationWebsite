---
name: php-backend
description: >-
  Backend PHP + PDO development standards for the Student Registration website.
  Use when creating, editing, or reviewing PHP endpoints, form handlers, CRUD
  operations (register, view, edit, delete), or database queries.
---

# PHP Backend Skill (Student Registration)

## Architecture & Scope
- **Stack:** Vanilla PHP running on WSL Apache (`/var/www/html`), connecting via PDO (`$pdo` from `db_config.php`) to an external MySQL/MariaDB VM.
- **Database boundary:** Do **not** alter database credentials, host IPs, or run schema migrations automatically unless explicitly requested—the user manages the VM database manually. Keep `db_config.php` untouched unless asked.

## Required Practices
1. **Prepared Statements Only:** Always use `$pdo->prepare(...)` with bound parameters (`:name`, `?`). Never interpolate `$_POST`, `$_GET`, or `$_REQUEST` directly into SQL strings.
2. **Input Validation & Trimming:**
   - Trim string inputs (`trim($_POST['field'] ?? '')`).
   - Validate required fields, email format (`filter_var($email, FILTER_VALIDATE_EMAIL)`), and allowed enum/select values (e.g., valid courses) server-side before querying.
   - Validate numeric IDs (`filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)` or `ctype_digit`) before `edit`/`delete` queries.
3. **Output Escaping (XSS Prevention):** Always escape dynamic values rendered into HTML with `htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8')`.
4. **State-Changing Requests:** Prefer `POST` for mutations (create, update, delete) and use Post-Redirect-Get (`header('Location: ...'); exit;`) after successful writes to prevent duplicate submissions on refresh.
5. **Error Handling:** Catch `PDOException` gracefully, display user-friendly feedback (`.error` / `.success`), and avoid exposing raw SQL or credentials in production-facing messages.
6. **Syncing to WSL:** After editing PHP files in this Windows workspace, remind the user (or run if approved) `sync-to-wsl.ps1` so Apache in WSL serves the latest changes.
