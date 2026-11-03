<?php
/**
 * API: Student Remaining Session Time Endpoint
 * Method: GET
 * Returns: JSON with remaining session seconds and warning state
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = current_user();
$studentId = $currentUser['id'];

// If administrator, allow checking another student
if (is_admin() && !empty($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
}

try {
    $session = get_student_active_session($studentId);

    if ($session) {
        $remaining = (int)$session['remaining_seconds'];
        echo json_encode([
            'success'              => true,
            'has_active_session'   => true,
            'session_id'           => $session['id'],
            'check_in_time'        => $session['check_in_time'],
            'expected_end_time'    => $session['expected_end_time'],
            'remaining_seconds'    => max(0, $remaining),
            'is_warning'           => ($remaining <= 900 && $remaining > 0), // 15 mins
            'is_expired'           => ($remaining <= 0)
        ]);
        exit;
    }

    // Check if there is an active reservation instead (waiting for check-in)
    $reservation = get_student_active_reservation($studentId);
    if ($reservation) {
        $remaining = (int)$reservation['remaining_seconds'];
        echo json_encode([
            'success'                => true,
            'has_active_session'     => false,
            'has_active_reservation' => true,
            'reservation_id'         => $reservation['id'],
            'reservation_end'        => $reservation['reservation_end'],
            'remaining_seconds'      => max(0, $remaining),
            'is_expired'             => ($remaining <= 0)
        ]);
        exit;
    }

    echo json_encode([
        'success'                => true,
        'has_active_session'     => false,
        'has_active_reservation' => false,
        'remaining_seconds'      => 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

