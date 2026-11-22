<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output safely for HTML rendering.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Check whether a registrar/admin user is signed in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

/**
 * Return the currently signed-in user array, or null.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Redirect unauthenticated visitors to login.php.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please sign in to access that page or perform that action.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Return (and lazily create) the session CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token in constant time.
 */
function verify_csrf(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || $token === null) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Store a one-time flash message in the session.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the current flash message.
 */
function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Record an audit entry in activity_logs without breaking primary flows.
 */
function log_activity(PDO $pdo, string $action, string $targetType, ?int $targetId, string $details): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, target_type, target_id, details)
             VALUES (:user_id, :action, :target_type, :target_id, :details)'
        );
        $stmt->execute([
            'user_id'     => $_SESSION['user']['id'] ?? null,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'details'     => substr($details, 0, 255),
        ]);
    } catch (PDOException $e) {
        // Keep primary CRUD functional even if logging fails.
    }
}

/**
 * Fetch all courses ordered alphabetically.
 */
function fetch_courses(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, code, name, department FROM courses ORDER BY name ASC');
    return $stmt->fetchAll();
}

/**
 * Allowed student status values.
 */
function allowed_statuses(): array
{
    return ['Active', 'Pending', 'Inactive', 'Graduated'];
}

/**
 * Allowed WMSU campus values.
 */
function allowed_campuses(): array
{
    return ['Main - Baliwasan', 'San Ramon', 'Curuan'];
}

/**
 * Allowed academic semester values.
 */
function allowed_semesters(): array
{
    return ['1st Semester', '2nd Semester', 'Summer'];
}

/**
 * Build parameterized student directory query from GET filter parameters.
 *
 * @return array{sql: string, params: array<string, mixed>, filters: array<string, string>}
 */
function build_student_filter_query(array $query): array
{
    $statuses = allowed_statuses();
    $campuses = allowed_campuses();

    $search = trim($query['q'] ?? '');
    $courseFilter = trim($query['course_id'] ?? '');
    $statusFilter = trim($query['status'] ?? '');
    $campusFilter = trim($query['campus'] ?? '');

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(s.full_name LIKE :q1 OR s.student_no LIKE :q2 OR s.email LIKE :q3)';
        $like = '%' . $search . '%';
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }

    if ($courseFilter !== '' && ctype_digit($courseFilter)) {
        $where[] = 's.course_id = :course_id';
        $params['course_id'] = (int)$courseFilter;
    }

    if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
        $where[] = 's.status = :status';
        $params['status'] = $statusFilter;
    }

    if ($campusFilter !== '' && in_array($campusFilter, $campuses, true)) {
        $where[] = 's.campus = :campus';
        $params['campus'] = $campusFilter;
    }

    $sql = 'SELECT s.id, s.student_no, s.full_name, s.email, s.course, s.year_level,
                   s.status, s.campus, s.school_year, s.semester, s.registration_date,
                   c.code AS course_code
            FROM students s
            LEFT JOIN courses c ON s.course_id = c.id';

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY s.registration_date DESC, s.id DESC';

    return [
        'sql'     => $sql,
        'params'  => $params,
        'filters' => [
            'q'         => $search,
            'course_id' => $courseFilter,
            'status'    => $statusFilter,
            'campus'    => $campusFilter,
        ],
    ];
}
