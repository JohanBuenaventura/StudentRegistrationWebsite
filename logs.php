<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$actionFilter = trim($_GET['action'] ?? '');
$availableActions = [
    'LOGIN_SUCCESS',
    'LOGIN_FAILED',
    'LOGOUT',
    'CREATE_STUDENT',
    'UPDATE_STUDENT',
    'DELETE_STUDENT',
    'CREATE_COURSE',
    'UPDATE_COURSE',
    'DELETE_COURSE',
    'EXPORT_CSV',
];

$sql = 'SELECT l.id, l.action, l.target_type, l.target_id, l.details, l.created_at,
               u.username, u.full_name
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id';
$params = [];

if ($actionFilter !== '' && in_array($actionFilter, $availableActions, true)) {
    $sql .= ' WHERE l.action = :action';
    $params['action'] = $actionFilter;
}

$sql .= ' ORDER BY l.created_at DESC, l.id DESC LIMIT 100';

$logs = [];
$queryError = '';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $queryError = 'Unable to load audit logs. Verify that schema.sql has been applied.';
}

/**
 * Map action names to badge color classes.
 */
function action_badge_class(string $action): string
{
    if (str_starts_with($action, 'CREATE_')) {
        return 'badge-action-create';
    }
    if (str_starts_with($action, 'UPDATE_')) {
        return 'badge-action-update';
    }
    if (str_starts_with($action, 'DELETE_')) {
        return 'badge-action-delete';
    }
    return 'badge-action';
}

render_header('Audit Logs', 'logs');
?>

<section class="card">
    <div class="card-header">
        <div>
            <h1>Registrar Audit Logs</h1>
            <p class="subtitle">Showing up to 100 most recent system events.</p>
        </div>
    </div>

    <form method="GET" action="logs.php" class="filter-bar">
        <div class="filter-field">
            <label for="action" class="sr-only">Filter by action type</label>
            <select id="action" name="action">
                <option value="">All Action Types</option>
                <?php foreach ($availableActions as $act): ?>
                    <option value="<?= e($act) ?>" <?= $act === $actionFilter ? 'selected' : '' ?>>
                        <?= e($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($actionFilter !== ''): ?>
                <a href="logs.php" class="btn btn-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($queryError !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($queryError) ?></div>
    <?php elseif (count($logs) === 0): ?>
        <div class="empty-state">
            <p>No audit log entries match your filter.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $actor = $log['full_name']
                            ? $log['full_name'] . ' (@' . $log['username'] . ')'
                            : 'System';
                        $target = $log['target_type'];
                        if ($log['target_id'] !== null) {
                            $target .= ' #' . (int)$log['target_id'];
                        }
                        ?>
                        <tr>
                            <td><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                            <td><?= e($actor) ?></td>
                            <td>
                                <span class="badge <?= e(action_badge_class($log['action'])) ?>">
                                    <?= e($log['action']) ?>
                                </span>
                            </td>
                            <td><code><?= e($target) ?></code></td>
                            <td><?= e($log['details']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
