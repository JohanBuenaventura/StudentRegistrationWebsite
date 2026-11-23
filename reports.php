<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

require_role('admin', 'registrar');

$summary = [
    'total_students'   => 0,
    'total_applicants' => 0,
    'approved_apps'    => 0,
    'pending_apps'     => 0,
];
$byCourse = [];
$byCampus = [];
$byYear = [];
$bySchoolYear = [];
$bySemester = [];

try {
    $summary['total_students'] = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

    $appCounts = $pdo->query(
        "SELECT
            COUNT(*) AS total_applicants,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_apps,
            SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'Requires Correction') THEN 1 ELSE 0 END) AS pending_apps
         FROM applications"
    )->fetch();
    if ($appCounts) {
        $summary['total_applicants'] = (int)$appCounts['total_applicants'];
        $summary['approved_apps'] = (int)$appCounts['approved_apps'];
        $summary['pending_apps'] = (int)$appCounts['pending_apps'];
    }

    $byCourse = $pdo->query(
        'SELECT c.code, c.name, COUNT(s.id) AS total
         FROM courses c
         LEFT JOIN students s ON s.course_id = c.id
         GROUP BY c.id, c.code, c.name
         ORDER BY total DESC, c.name ASC'
    )->fetchAll();

    $byCampus = $pdo->query(
        'SELECT campus AS label, COUNT(*) AS total FROM students GROUP BY campus ORDER BY total DESC'
    )->fetchAll();

    $byYear = $pdo->query(
        'SELECT CONCAT("Year ", year_level) AS label, COUNT(*) AS total FROM students GROUP BY year_level ORDER BY year_level ASC'
    )->fetchAll();

    $bySchoolYear = $pdo->query(
        'SELECT school_year AS label, COUNT(*) AS total FROM students GROUP BY school_year ORDER BY school_year DESC'
    )->fetchAll();

    $bySemester = $pdo->query(
        'SELECT semester AS label, COUNT(*) AS total FROM students GROUP BY semester ORDER BY total DESC'
    )->fetchAll();
} catch (PDOException $e) {
    set_flash('error', 'Unable to load enrollment reports.');
}

if (($_GET['export'] ?? '') === 'csv') {
    log_activity($pdo, 'EXPORT_REPORT_CSV', 'report', null, 'Exported enrollment summary report CSV');

    $filename = 'wmsu-enrollment-report-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Category', 'Group', 'Count']);
    fputcsv($out, ['Overview', 'Total Registered Students', $summary['total_students']]);
    fputcsv($out, ['Overview', 'Total Applicants', $summary['total_applicants']]);
    fputcsv($out, ['Overview', 'Approved Applications', $summary['approved_apps']]);
    fputcsv($out, ['Overview', 'Pending Applications', $summary['pending_apps']]);

    foreach ($byCourse as $row) {
        fputcsv($out, ['By Course', $row['code'] . ' — ' . $row['name'], (int)$row['total']]);
    }
    foreach ($byCampus as $row) {
        fputcsv($out, ['By Campus', $row['label'], (int)$row['total']]);
    }
    foreach ($byYear as $row) {
        fputcsv($out, ['By Year Level', $row['label'], (int)$row['total']]);
    }
    foreach ($bySchoolYear as $row) {
        fputcsv($out, ['By School Year', $row['label'], (int)$row['total']]);
    }
    foreach ($bySemester as $row) {
        fputcsv($out, ['By Semester', $row['label'], (int)$row['total']]);
    }
    fclose($out);
    exit;
}

render_header('Enrollment Reports', 'reports');
?>

<section class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div>
            <h1>WMSU Enrollment &amp; Application Reports</h1>
            <p class="subtitle">Generated <?= e(date('F j, Y g:i A')) ?></p>
        </div>
        <div class="header-actions no-print">
            <a href="reports.php?export=csv" class="btn btn-secondary btn-sm">Export CSV</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print Report</button>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom:0;">
        <article class="stat-card">
            <span class="stat-label">Registered Students</span>
            <strong class="stat-value"><?= $summary['total_students'] ?></strong>
        </article>
        <article class="stat-card">
            <span class="stat-label">Total Applicants</span>
            <strong class="stat-value"><?= $summary['total_applicants'] ?></strong>
        </article>
        <article class="stat-card">
            <span class="stat-label">Approved Applications</span>
            <strong class="stat-value"><?= $summary['approved_apps'] ?></strong>
        </article>
        <article class="stat-card">
            <span class="stat-label">Pending Applications</span>
            <strong class="stat-value"><?= $summary['pending_apps'] ?></strong>
        </article>
    </div>
</section>

<div class="reports-grid">
    <section class="card">
        <h2 class="section-subtitle">Enrollment by Course</h2>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Program</th><th>Students</th></tr></thead>
                <tbody>
                    <?php foreach ($byCourse as $row): ?>
                        <tr><td><?= e($row['code'] . ' — ' . $row['name']) ?></td><td><strong><?= (int)$row['total'] ?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="section-subtitle">Enrollment by Campus</h2>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Campus</th><th>Students</th></tr></thead>
                <tbody>
                    <?php foreach ($byCampus as $row): ?>
                        <tr><td><?= e($row['label']) ?></td><td><strong><?= (int)$row['total'] ?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="section-subtitle">Enrollment by Year Level</h2>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Year Level</th><th>Students</th></tr></thead>
                <tbody>
                    <?php foreach ($byYear as $row): ?>
                        <tr><td><?= e($row['label']) ?></td><td><strong><?= (int)$row['total'] ?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="section-subtitle">Enrollment by School Year &amp; Semester</h2>
        <div class="table-responsive" style="margin-bottom:1rem;">
            <table>
                <thead><tr><th>School Year</th><th>Students</th></tr></thead>
                <tbody>
                    <?php foreach ($bySchoolYear as $row): ?>
                        <tr><td><?= e($row['label']) ?></td><td><strong><?= (int)$row['total'] ?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Semester</th><th>Students</th></tr></thead>
                <tbody>
                    <?php foreach ($bySemester as $row): ?>
                        <tr><td><?= e($row['label']) ?></td><td><strong><?= (int)$row['total'] ?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php render_footer(); ?>
