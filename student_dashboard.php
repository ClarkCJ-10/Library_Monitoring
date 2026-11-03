<?php
/**
 * Student Dashboard
 */

$pageTitle = "Student Dashboard";
$activeNav = "dashboard";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_student();

$currentUser = current_user();
$studentId = $currentUser['id'];

// Get seat counts (auto-cleans expired items)
$seatCounts = get_seat_counts();
$settings = get_system_settings();

// Check student's current state
$activeSession = get_student_active_session($studentId);
$activeReservation = null;
if (!$activeSession) {
    $activeReservation = get_student_active_reservation($studentId);
}

// Fetch Student's recent reservation history
$db = get_db_connection();
$stmtHist = $db->prepare("
    SELECT r.id, r.reservation_start, r.reservation_end, r.status,
           s.check_in_time, s.check_out_time, s.status AS session_status,
           TIMESTAMPDIFF(MINUTE, s.check_in_time, s.check_out_time) AS duration_minutes
    FROM reservations r
    LEFT JOIN sessions s ON s.reservation_id = r.id
    WHERE r.student_id = ?
    ORDER BY r.id DESC
    LIMIT 10
");
$stmtHist->execute([$studentId]);
$historyRows = $stmtHist->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-intro">
    <h2>
        Welcome back, <?= e($currentUser['full_name']); ?>! 👋
        <?php if (!empty($currentUser['course'])): ?>
            <span class="badge" style="font-size: 13px; font-weight: 600; vertical-align: middle; background: #e0f2fe; color: #0369a1;">
                🎓 <?= e($currentUser['course']); ?>
            </span>
        <?php endif; ?>
    </h2>
    <p>Monitor library seat availability, manage your current seat booking, and track your session time.</p>
</div>

<!-- 4 Numerical Seat Availability Cards (NO seat map, NO physical location) -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="stat-icon-wrapper">🪑</div>
        <div class="stat-info">
            <span class="stat-label">Total Library Seats</span>
            <span class="stat-value" id="stat-total"><?= $seatCounts['total']; ?></span>
        </div>
    </div>

    <div class="stat-card available">
        <div class="stat-icon-wrapper">✅</div>
        <div class="stat-info">
            <span class="stat-label">Available Seats</span>
            <span class="stat-value" id="stat-available"><?= $seatCounts['available']; ?></span>
        </div>
    </div>

    <div class="stat-card reserved">
        <div class="stat-icon-wrapper">📌</div>
        <div class="stat-info">
            <span class="stat-label">Reserved Seats</span>
            <span class="stat-value" id="stat-reserved"><?= $seatCounts['reserved']; ?></span>
        </div>
    </div>

    <div class="stat-card occupied">
        <div class="stat-icon-wrapper">👤</div>
        <div class="stat-info">
            <span class="stat-label">Occupied Seats</span>
            <span class="stat-value" id="stat-occupied"><?= $seatCounts['occupied']; ?></span>
        </div>
    </div>
</div>

<!-- Current Status & Action Banner -->
<?php if ($activeSession): ?>
    <!-- 1. CHECKED-IN STATE (Active Session) -->
    <div class="action-banner occupied-state">
        <div style="font-size: 32px; margin-bottom: 8px;">📖</div>
        <h3>You are Checked In (Library Session Active)</h3>
        <p>Your seat is confirmed and your library session countdown is running. Make sure to check out when leaving the library.</p>

        <!-- Big Session Countdown Timer -->
        <div class="countdown-box" id="student-countdown-box">
            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-600); margin-bottom: 6px;">
                Remaining Library Session Time
            </div>
            <div class="countdown-digits" id="session-countdown-display" data-countdown-end="<?= date('c', strtotime($activeSession['expected_end_time'])); ?>">
                --:--:--
            </div>

            <!-- Warning Alert (shown dynamically when <= 15 min or immediately if already <= 15 min) -->
            <div id="session-warning-banner" class="timer-warning-alert <?= ($activeSession['remaining_seconds'] <= 900) ? '' : 'style-hidden'; ?>" style="<?= ($activeSession['remaining_seconds'] <= 900) ? '' : 'display: none;'; ?>">
                ⚠️ Warning: Less than 15 minutes remaining in your session!
            </div>
        </div>

        <div style="margin: 18px 0 10px; font-size: 14px; color: var(--gray-600);">
            <span><strong>Checked In:</strong> <?= date('g:i A', strtotime($activeSession['check_in_time'])); ?></span>
            &nbsp;•&nbsp;
            <span><strong>Expected End:</strong> <?= date('g:i A', strtotime($activeSession['expected_end_time'])); ?></span>
        </div>

        <!-- Check Out Form -->
        <form action="<?= BASE_URL; ?>check_out.php" method="POST" id="checkout-form" style="margin-top: 16px;">
            <?= csrf_field(); ?>
            <button type="button" class="btn btn-danger btn-lg" onclick="confirmModal('Are you sure you want to end your library session and check out now?', () => document.getElementById('checkout-form').submit());">
                🚪 End Session & Check Out
            </button>
        </form>
    </div>

<?php elseif ($activeReservation): ?>
    <!-- 2. RESERVED STATE (Waiting for Check-In) -->
    <div class="action-banner reserved-state">
        <div style="font-size: 32px; margin-bottom: 8px;">⏳</div>
        <h3>Seat Reserved — Ready for Check-In</h3>
        <p>A library seat has been booked for you. You must check in before your reservation window expires, otherwise the seat will be automatically released.</p>

        <div class="countdown-box" id="reservation-countdown-box">
            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--warning); margin-bottom: 6px;">
                Check-In Window Closes In
            </div>
            <div class="countdown-digits" id="reservation-countdown-display" style="color: var(--warning); border-color: var(--warning-border);" data-countdown-end="<?= date('c', strtotime($activeReservation['reservation_end'])); ?>">
                --:--:--
            </div>
        </div>

        <div style="margin: 14px 0 20px; font-size: 14px; color: var(--gray-600);">
            <span><strong>Booked At:</strong> <?= date('g:i A', strtotime($activeReservation['reservation_start'])); ?></span>
            &nbsp;•&nbsp;
            <span><strong>Expires At:</strong> <?= date('g:i A', strtotime($activeReservation['reservation_end'])); ?></span>
        </div>

        <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
            <!-- Check In Form -->
            <form action="<?= BASE_URL; ?>check_in.php" method="POST">
                <?= csrf_field(); ?>
                <button type="submit" class="btn btn-success btn-lg">
                    ✅ Check In Now
                </button>
            </form>

            <!-- Cancel Form -->
            <form action="<?= BASE_URL; ?>cancel_reservation.php" method="POST" id="cancel-res-form">
                <?= csrf_field(); ?>
                <button type="button" class="btn btn-outline btn-lg" onclick="confirmModal('Are you sure you want to cancel your seat reservation?', () => document.getElementById('cancel-res-form').submit());">
                    ❌ Cancel Reservation
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- 3. NO ACTIVE RESERVATION -->
    <div class="action-banner">
        <div style="font-size: 32px; margin-bottom: 8px;">🪑</div>
        <h3>Reserve a Library Seat</h3>
        <p>Need a quiet place to study? Check real-time seat availability and book an open seat instantly. Once booked, you have <strong><?= (int)$settings['reservation_expiration_minutes']; ?> minutes</strong> to check in.</p>

        <?php if ($seatCounts['available'] > 0): ?>
            <form action="<?= BASE_URL; ?>reserve_seat.php" method="POST" id="reserve-seat-form">
                <?= csrf_field(); ?>
                <button type="button" class="btn btn-primary btn-lg" onclick="confirmModal('Confirm reservation for 1 available seat? You will have <?= (int)$settings['reservation_expiration_minutes']; ?> minutes to check in.', () => document.getElementById('reserve-seat-form').submit());">
                    ✨ Reserve Available Seat (<span id="live-available-count"><?= $seatCounts['available']; ?></span> Open)
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning" style="display: inline-flex; margin: 10px 0 0;">
                <span>⚠️</span>
                <div>All library seats are currently occupied or reserved. Please wait for students to check out.</div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Important Notices & Library Guidelines Card -->
<div class="card">
    <div class="card-header">
        <h3>📢 Library Information & Guidelines</h3>
    </div>
    <div class="card-body" style="font-size: 14px; color: var(--gray-700); line-height: 1.7;">
        <ul style="padding-left: 20px;">
            <li><strong>Session Duration:</strong> Maximum study session duration is <strong><?= (int)$settings['session_duration_minutes']; ?> minutes</strong> (<?= round($settings['session_duration_minutes'] / 60, 1); ?> hours).</li>
            <li><strong>Check-in Deadline:</strong> You must check in within <strong><?= (int)$settings['reservation_expiration_minutes']; ?> minutes</strong> after reserving. Unclaimed seats are automatically cancelled and released.</li>
            <li><strong>Fair Use Policy:</strong> Only one active reservation or session is permitted per student at any time.</li>
            <li><strong>Seat Assignment:</strong> Seats are assigned automatically by the system. Location details are handled by library staff upon entry.</li>
            <li><strong>Early Departure:</strong> Please remember to click <em>"End Session & Check Out"</em> when leaving so another student can use the seat.</li>
        </ul>
    </div>
</div>

<!-- Reservation History Card -->
<div class="card">
    <div class="card-header">
        <h3>📋 My Recent Reservation History</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyRows)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No reservation history recorded yet. Reserve your first seat above!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historyRows as $row): ?>
                        <tr>
                            <td><strong>#<?= $row['id']; ?></strong></td>
                            <td><?= date('M d, Y', strtotime($row['reservation_start'])); ?></td>
                            <td><?= date('g:i A', strtotime($row['reservation_start'])); ?></td>
                            <td><?= $row['check_in_time'] ? date('g:i A', strtotime($row['check_in_time'])) : '—'; ?></td>
                            <td><?= $row['check_out_time'] ? date('g:i A', strtotime($row['check_out_time'])) : '—'; ?></td>
                            <td><?= $row['duration_minutes'] !== null ? $row['duration_minutes'] . ' mins' : '—'; ?></td>
                            <td>
                                <span class="badge badge-<?= e($row['status']); ?>">
                                    <?= ucfirst(e($row['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Specific Student Dashboard Countdown Setup
document.addEventListener('DOMContentLoaded', function() {
    const sessionDisplay = document.getElementById('session-countdown-display');
    const warningBanner = document.getElementById('session-warning-banner');

    if (sessionDisplay) {
        const targetIso = sessionDisplay.getAttribute('data-countdown-end');
        if (targetIso) {
            new LibraryCountdown({
                targetTimestamp: targetIso,
                displayElement: sessionDisplay,
                warningSeconds: 900, // 15 mins
                onWarning: function() {
                    if (warningBanner) {
                        warningBanner.style.display = 'inline-flex';
                    }
                    showToast('Less than 15 minutes remaining in your library session!', 'warning');
                },
                onExpire: function() {
                    showToast('Your library session time has expired. The seat will now be released.', 'error');
                    setTimeout(function() {
                        window.location.reload();
                    }, 2500);
                }
            });
        }
    }

    const resDisplay = document.getElementById('reservation-countdown-display');
    if (resDisplay) {
        const targetResIso = resDisplay.getAttribute('data-countdown-end');
        if (targetResIso) {
            new LibraryCountdown({
                targetTimestamp: targetResIso,
                displayElement: resDisplay,
                warningSeconds: 60, // 1 min left
                onExpire: function() {
                    showToast('Reservation window expired. Seat released.', 'error');
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                }
            });
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

