<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid session token.');
        header('Location: notifications.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'mark_all_read') {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
            $stmt->execute(['user_id' => $userId]);
            set_flash('success', 'All notifications marked as read.');
        } elseif ($action === 'mark_read') {
            $notifId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($notifId && $notifId > 0) {
                $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $notifId, 'user_id' => $userId]);
            }
        }
    } catch (PDOException $e) {
        set_flash('error', 'Unable to update notifications.');
    }

    header('Location: notifications.php');
    exit;
}

$notifications = [];
$unreadCount = 0;
try {
    $stmt = $pdo->prepare(
        'SELECT n.*, a.reference_no
         FROM notifications n
         LEFT JOIN applications a ON n.application_id = a.id
         WHERE n.user_id = :user_id
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT 100'
    );
    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll();

    foreach ($notifications as $n) {
        if (!(int)$n['is_read']) {
            $unreadCount++;
        }
    }
} catch (PDOException $e) {
    set_flash('error', 'Unable to load notifications.');
}

render_header('Notifications', 'notifications');
?>

<section class="card">
    <div class="card-header">
        <div>
            <h1>Notifications</h1>
            <p class="subtitle"><?= $unreadCount ?> unread notification<?= $unreadCount === 1 ? '' : 's' ?>.</p>
        </div>
        <div class="header-actions">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" action="notifications.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-primary btn-sm">Mark All Read</button>
                </form>
            <?php endif; ?>
            <a href="<?= e(role_home_url()) ?>" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <p>You have no notifications yet.</p>
        </div>
    <?php else: ?>
        <div class="req-list">
            <?php foreach ($notifications as $notif): ?>
                <div class="req-item <?= !(int)$notif['is_read'] ? 'notif-unread' : '' ?>">
                    <div>
                        <strong><?= e($notif['title']) ?></strong>
                        <?php if (!(int)$notif['is_read']): ?>
                            <span class="badge badge-pending">New</span>
                        <?php endif; ?>
                        <p class="subtitle"><?= e($notif['message']) ?></p>
                        <small class="text-muted-link">
                            <?= e(date('M j, Y g:i A', strtotime($notif['created_at']))) ?>
                            <?php if (!empty($notif['application_id']) && has_role('student')): ?>
                                &middot; <a href="student/track.php?id=<?= (int)$notif['application_id'] ?>">Track <?= e($notif['reference_no'] ?? 'Application') ?></a>
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!(int)$notif['is_read']): ?>
                        <form method="POST" action="notifications.php" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="id" value="<?= (int)$notif['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Mark Read</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
