<?php
/**
 * Administrator: Library Usage Reports & Analytics
 */

$pageTitle = "Usage Reports";
$activeNav = "reports";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();

// Date range filters (default: last 30 days)
$startDate = trim($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));

// Check for CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=library_usage_report_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Reservation ID', 'Student ID', 'Student Name', 'Course', 'Seat Code', 'Start Time', 'End Time', 'Check-In Time', 'Check-Out Time', 'Duration (Minutes)', 'Status']);

    $stmtExport = $db->prepare("
        SELECT r.id, st.student_id, st.full_name, COALESCE(st.course, 'N/A') AS course_name, se.seat_number,
               r.reservation_start, r.reservation_end,
               s.check_in_time, s.check_out_time,
               TIMESTAMPDIFF(MINUTE, s.check_in_time, s.check_out_time) AS duration_mins,
               r.status
        FROM reservations r
        JOIN students st ON st.id = r.student_id
        JOIN seats se ON se.id = r.seat_id
        LEFT JOIN sessions s ON s.reservation_id = r.id
        WHERE DATE(r.reservation_start) BETWEEN ? AND ?
        ORDER BY r.id DESC
    ");
    $stmtExport->execute([$startDate, $endDate]);

    while ($row = $stmtExport->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Summary Metrics for Date Range
$stmtSummary = $db->prepare("
    SELECT 
        COUNT(*) AS total_reservations,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied_count,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_count
    FROM reservations
    WHERE DATE(reservation_start) BETWEEN ? AND ?
");
$stmtSummary->execute([$startDate, $endDate]);
$summary = $stmtSummary->fetch();

// Daily Breakdown
$stmtDaily = $db->prepare("
    SELECT 
        DATE(r.reservation_start) AS usage_date,
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN r.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN r.status = 'expired' THEN 1 ELSE 0 END) AS expired,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, s.check_in_time, s.check_out_time)), 0) AS avg_duration_mins
    FROM reservations r
    LEFT JOIN sessions s ON s.reservation_id = r.id AND s.status = 'completed'
    WHERE DATE(r.reservation_start) BETWEEN ? AND ?
    GROUP BY DATE(r.reservation_start)
    ORDER BY usage_date DESC
");
$stmtDaily->execute([$startDate, $endDate]);
$dailyRows = $stmtDaily->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-intro" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2>Library Usage Reports & Analytics</h2>
        <p>Review historical bookings, completion rates, and export activity data.</p>
    </div>
    <div>
        <a href="<?= BASE_URL; ?>admin/reports.php?start_date=<?= urlencode($startDate); ?>&end_date=<?= urlencode($endDate); ?>&export=csv" class="btn btn-primary">
            📥 Export CSV Report
        </a>
    </div>
</div>

<!-- Date Filter Form -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="<?= BASE_URL; ?>admin/reports.php" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;">
            <div>
                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate); ?>" required>
            </div>
            <div>
                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate); ?>" required>
            </div>
            <div>
                <button type="submit" class="btn btn-outline">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper" style="background: var(--primary-light); color: var(--primary);">📋</div>
        <div class="stat-info">
            <span class="stat-label">Total Bookings</span>
            <span class="stat-value"><?= (int)($summary['total_reservations'] ?? 0); ?></span>
        </div>
    </div>

    <div class="stat-card available">
        <div class="stat-icon-wrapper">✅</div>
        <div class="stat-info">
            <span class="stat-label">Completed</span>
            <span class="stat-value"><?= (int)($summary['completed_count'] ?? 0); ?></span>
        </div>
    </div>

    <div class="stat-card reserved">
        <div class="stat-icon-wrapper">📌</div>
        <div class="stat-info">
            <span class="stat-label">Active / Pending</span>
            <span class="stat-value"><?= (int)(($summary['reserved_count'] ?? 0) + ($summary['occupied_count'] ?? 0)); ?></span>
        </div>
    </div>

    <div class="stat-card occupied">
        <div class="stat-icon-wrapper">❌</div>
        <div class="stat-info">
            <span class="stat-label">Cancelled / Expired</span>
            <span class="stat-value"><?= (int)(($summary['cancelled_count'] ?? 0) + ($summary['expired_count'] ?? 0)); ?></span>
        </div>
    </div>
</div>

<!-- Daily Usage Breakdown -->
<div class="card">
    <div class="card-header">
        <h3>📅 Daily Activity Breakdown</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Bookings</th>
                    <th>Completed Sessions</th>
                    <th>Cancelled</th>
                    <th>Expired Check-ins</th>
                    <th>Avg Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dailyRows)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No library activity recorded for the selected date range.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dailyRows as $d): ?>
                        <tr>
                            <td><strong><?= date('M d, Y (l)', strtotime($d['usage_date'])); ?></strong></td>
                            <td><?= $d['total_bookings']; ?></td>
                            <td><span class="badge badge-success"><?= $d['completed']; ?></span></td>
                            <td><?= $d['cancelled']; ?></td>
                            <td><?= $d['expired']; ?></td>
                            <td><?= $d['avg_duration_mins'] ? $d['avg_duration_mins'] . ' mins' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

