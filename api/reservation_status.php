<?php
/**
 * API: Student Reservation Status Endpoint
 * Method: GET
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

if (is_admin() && !empty($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
}

try {
    $activeSession = get_student_active_session($studentId);
    if ($activeSession) {
        echo json_encode([
            'success'     => true,
            'state'       => 'occupied',
            'session_id'  => $activeSession['id'],
            'check_in'    => $activeSession['check_in_time'],
            'expected_end'=> $activeSession['expected_end_time'],
            'remaining'   => max(0, (int)$activeSession['remaining_seconds'])
        ]);
        exit;
    }

    $activeRes = get_student_active_reservation($studentId);
    if ($activeRes) {
        echo json_encode([
            'success'        => true,
            'state'          => 'reserved',
            'reservation_id' => $activeRes['id'],
            'start_time'     => $activeRes['reservation_start'],
            'end_time'       => $activeRes['reservation_end'],
            'remaining'      => max(0, (int)$activeRes['remaining_seconds'])
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'state'   => 'none'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

