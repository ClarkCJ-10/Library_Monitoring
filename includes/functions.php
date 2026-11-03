<?php
/**
 * Library Seat Reservation and Monitoring System
 * Core Business Logic & Helper Functions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

/**
 * HTML Escaping for XSS prevention
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Valid Academic Courses for Student Enrollment
 */
function get_course_list(): array {
    return [
        'BS Information Technology' => 'BS in Information Technology (BSIT)',
        'BS Computer Science' => 'BS in Computer Science (BSCS)',
        'BS Information Systems' => 'BS in Information Systems (BSIS)',
        'BS Computer Engineering' => 'BS in Computer Engineering (BSCpE)',
        'BS Civil Engineering' => 'BS in Civil Engineering (BSCE)',
        'BS Electrical Engineering' => 'BS in Electrical Engineering (BSEE)',
        'BS Mechanical Engineering' => 'BS in Mechanical Engineering (BSME)',
        'BS Business Administration' => 'BS in Business Administration (BSBA)',
        'BS Accountancy' => 'BS in Accountancy (BSA)',
        'BS Hospitality Management' => 'BS in Hospitality Management (BSHM)',
        'BS Tourism Management' => 'BS in Tourism Management (BSTM)',
        'BS Nursing' => 'BS in Nursing (BSN)',
        'BS Medical Technology' => 'BS in Medical Technology (BSMT)',
        'BS Pharmacy' => 'BS in Pharmacy (BSP)',
        'BS Psychology' => 'BS in Psychology (BSPsych)',
        'BS Criminology' => 'BS in Criminology (BSCrim)',
        'Bachelor of Elementary Education' => 'Bachelor of Elementary Education (BEEd)',
        'Bachelor of Secondary Education' => 'Bachelor of Secondary Education (BSEd)',
        'Bachelor of Arts in Communication' => 'BA in Communication (BAComm)',
        'BS Architecture' => 'BS in Architecture (BSArch)'
    ];
}

/**
 * Validate Gmail Address (@gmail.com)
 */
function is_valid_gmail(string $email): bool {
    $trimmed = trim($email);
    return (bool)filter_var($trimmed, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/i', $trimmed);
}

/**
 * Get Client IP Address
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log System Audit Events
 */
function log_audit(string $action, ?string $details = null, ?int $userId = null): void {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $details, get_client_ip()]);
    } catch (Exception $e) {
        error_log("Failed to log audit event: " . $e->getMessage());
    }
}

/**
 * Get System Settings
 */
function get_system_settings(): array {
    $db = get_db_connection();
    $stmt = $db->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch();

    if (!$settings) {
        $db->query("INSERT INTO settings (id, session_duration_minutes, reservation_expiration_minutes, library_open_time, library_close_time) VALUES (1, 120, 5, '08:00:00', '18:00:00')");
        return [
            'id' => 1,
            'session_duration_minutes' => 120,
            'reservation_expiration_minutes' => 5,
            'library_open_time' => '08:00:00',
            'library_close_time' => '18:00:00'
        ];
    }

    return $settings;
}

/**
 * Release Expired Reservations and Sessions
 * Critical function that keeps database and seat states completely consistent
 */
function release_expired_reservations_and_sessions(): void {
    $db = get_db_connection();

    try {
        // 1. Expire un-checked-in reservations past their deadline
        $stmtExpRes = $db->query("
            SELECT r.id, r.seat_id, r.student_id 
            FROM reservations r
            WHERE r.status = 'reserved' AND r.reservation_end <= NOW()
        ");
        $expiredReservations = $stmtExpRes->fetchAll();

        foreach ($expiredReservations as $res) {
            $db->beginTransaction();
            try {
                // Update reservation status to expired
                $upd = $db->prepare("UPDATE reservations SET status = 'expired' WHERE id = ?");
                $upd->execute([$res['id']]);

                // Release seat back to available if no other active reservation/session
                $checkActive = $db->prepare("
                    SELECT COUNT(*) FROM reservations 
                    WHERE seat_id = ? AND status IN ('reserved', 'occupied') AND id != ?
                ");
                $checkActive->execute([$res['seat_id'], $res['id']]);
                if ($checkActive->fetchColumn() == 0) {
                    $updSeat = $db->prepare("UPDATE seats SET seat_status = 'available' WHERE id = ?");
                    $updSeat->execute([$res['seat_id']]);
                }

                $db->commit();
                log_audit('reservation_expired', "Reservation #{$res['id']} expired automatically.", $res['student_id']);
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error expiring reservation #{$res['id']}: " . $e->getMessage());
            }
        }

        // 2. Expire active sessions past expected_end_time
        $stmtExpSess = $db->query("
            SELECT s.id, s.seat_id, s.student_id, s.reservation_id, s.expected_end_time
            FROM sessions s
            WHERE s.status = 'active' AND s.expected_end_time <= NOW()
        ");
        $expiredSessions = $stmtExpSess->fetchAll();

        foreach ($expiredSessions as $sess) {
            $db->beginTransaction();
            try {
                // Mark session as expired
                $updSess = $db->prepare("UPDATE sessions SET status = 'expired', check_out_time = expected_end_time WHERE id = ?");
                $updSess->execute([$sess['id']]);

                // Mark reservation as completed/expired
                $updRes = $db->prepare("UPDATE reservations SET status = 'completed' WHERE id = ?");
                $updRes->execute([$sess['reservation_id']]);

                // Release seat
                $updSeat = $db->prepare("UPDATE seats SET seat_status = 'available' WHERE id = ?");
                $updSeat->execute([$sess['seat_id']]);

                $db->commit();
                log_audit('session_expired', "Session #{$sess['id']} completed due to time expiration.", $sess['student_id']);
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error expiring session #{$sess['id']}: " . $e->getMessage());
            }
        }

        // 3. Self-healing seat state consistency check
        // Make sure seats marked 'reserved' actually have an active 'reserved' reservation
        $db->query("
            UPDATE seats s
            SET s.seat_status = 'available'
            WHERE s.seat_status = 'reserved'
              AND s.id NOT IN (SELECT seat_id FROM reservations WHERE status = 'reserved')
        ");

        // Make sure seats marked 'occupied' actually have an active 'active' session
        $db->query("
            UPDATE seats s
            SET s.seat_status = 'available'
            WHERE s.seat_status = 'occupied'
              AND s.id NOT IN (SELECT seat_id FROM sessions WHERE status = 'active')
        ");

    } catch (Exception $e) {
        error_log("Error during release_expired_reservations_and_sessions: " . $e->getMessage());
    }
}

/**
 * Get Dynamic Seat Availability Counts
 */
function get_seat_counts(): array {
    release_expired_reservations_and_sessions();

    $db = get_db_connection();

    $stmtTotal = $db->query("SELECT COUNT(*) FROM seats WHERE is_active = 1");
    $total = (int)$stmtTotal->fetchColumn();

    $stmtAvail = $db->query("SELECT COUNT(*) FROM seats WHERE is_active = 1 AND seat_status = 'available'");
    $available = (int)$stmtAvail->fetchColumn();

    $stmtRes = $db->query("SELECT COUNT(*) FROM seats WHERE is_active = 1 AND seat_status = 'reserved'");
    $reserved = (int)$stmtRes->fetchColumn();

    $stmtOcc = $db->query("SELECT COUNT(*) FROM seats WHERE is_active = 1 AND seat_status = 'occupied'");
    $occupied = (int)$stmtOcc->fetchColumn();

    $stmtDeact = $db->query("SELECT COUNT(*) FROM seats WHERE is_active = 0");
    $deactivated = (int)$stmtDeact->fetchColumn();

    return [
        'total'       => $total,
        'available'   => $available,
        'reserved'    => $reserved,
        'occupied'    => $occupied,
        'deactivated' => $deactivated
    ];
}

/**
 * Get Active Reservation for Student (status = 'reserved')
 */
function get_student_active_reservation(int $student_id): ?array {
    release_expired_reservations_and_sessions();
    $db = get_db_connection();

    $stmt = $db->prepare("
        SELECT r.*, TIMESTAMPDIFF(SECOND, NOW(), r.reservation_end) AS remaining_seconds
        FROM reservations r
        WHERE r.student_id = ? AND r.status = 'reserved'
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $res = $stmt->fetch();

    return $res ?: null;
}

/**
 * Get Active Session for Student (status = 'active')
 */
function get_student_active_session(int $student_id): ?array {
    release_expired_reservations_and_sessions();
    $db = get_db_connection();

    $stmt = $db->prepare("
        SELECT s.*, TIMESTAMPDIFF(SECOND, NOW(), s.expected_end_time) AS remaining_seconds
        FROM sessions s
        WHERE s.student_id = ? AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $session = $stmt->fetch();

    return $session ?: null;
}

/**
 * Reserve an Available Seat for a Student
 * Uses strict MySQL Transaction and SELECT FOR UPDATE to prevent concurrency double-booking
 */
function reserve_seat_for_student(int $student_id): array {
    release_expired_reservations_and_sessions();
    $db = get_db_connection();

    $db->beginTransaction();

    try {
        // 1. Verify student account is active
        $stmtStudent = $db->prepare("SELECT id, full_name, is_active FROM students WHERE id = ? FOR UPDATE");
        $stmtStudent->execute([$student_id]);
        $student = $stmtStudent->fetch();

        if (!$student || !$student['is_active']) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Student account is inactive or not found.'];
        }

        // 2. Verify student does not already have an active reservation or session
        $stmtCheckRes = $db->prepare("SELECT id FROM reservations WHERE student_id = ? AND status = 'reserved' FOR UPDATE");
        $stmtCheckRes->execute([$student_id]);
        if ($stmtCheckRes->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'You already have an active reservation waiting for check-in.'];
        }

        $stmtCheckSess = $db->prepare("SELECT id FROM sessions WHERE student_id = ? AND status = 'active' FOR UPDATE");
        $stmtCheckSess->execute([$student_id]);
        if ($stmtCheckSess->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'You currently have an active library session. Please check out first.'];
        }

        // 3. Find and lock an available active seat
        $stmtSeat = $db->prepare("
            SELECT id FROM seats 
            WHERE seat_status = 'available' AND is_active = 1 
            ORDER BY id ASC 
            LIMIT 1 
            FOR UPDATE
        ");
        $stmtSeat->execute();
        $seat = $stmtSeat->fetch();

        if (!$seat) {
            $db->rollBack();
            return ['success' => false, 'message' => 'No seats are currently available. Please try again later.'];
        }

        $seat_id = (int)$seat['id'];

        // 4. Retrieve settings for reservation expiration
        $settings = get_system_settings();
        $expirationMinutes = (int)($settings['reservation_expiration_minutes'] ?? 5);

        // 5. Update seat status to 'reserved'
        $stmtUpdSeat = $db->prepare("UPDATE seats SET seat_status = 'reserved' WHERE id = ?");
        $stmtUpdSeat->execute([$seat_id]);

        // 6. Create reservation record
        $stmtInsert = $db->prepare("
            INSERT INTO reservations (student_id, seat_id, reservation_start, reservation_end, status, created_at)
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), 'reserved', NOW())
        ");
        $stmtInsert->execute([$student_id, $seat_id, $expirationMinutes]);
        $reservation_id = (int)$db->lastInsertId();

        $db->commit();

        log_audit('seat_reserved', "Student reserved a seat (Reservation #{$reservation_id})", $student_id);

        return [
            'success'        => true,
            'message'        => 'Seat reserved successfully! Please check in within ' . $expirationMinutes . ' minutes.',
            'reservation_id' => $reservation_id,
            'expires_in'     => $expirationMinutes * 60
        ];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Reservation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to reserve seat. Please try again.'];
    }
}

/**
 * Cancel a Student's Active Reservation
 */
function cancel_reservation(int $student_id, ?int $reservation_id = null, bool $is_admin = false): array {
    $db = get_db_connection();
    $db->beginTransaction();

    try {
        if ($reservation_id !== null && $is_admin) {
            $stmt = $db->prepare("SELECT * FROM reservations WHERE id = ? AND status = 'reserved' FOR UPDATE");
            $stmt->execute([$reservation_id]);
        } else {
            $stmt = $db->prepare("SELECT * FROM reservations WHERE student_id = ? AND status = 'reserved' FOR UPDATE");
            $stmt->execute([$student_id]);
        }

        $reservation = $stmt->fetch();
        if (!$reservation) {
            $db->rollBack();
            return ['success' => false, 'message' => 'No active reservation found to cancel.'];
        }

        // Update reservation to cancelled
        $updRes = $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
        $updRes->execute([$reservation['id']]);

        // Release seat to available
        $updSeat = $db->prepare("UPDATE seats SET seat_status = 'available' WHERE id = ?");
        $updSeat->execute([$reservation['seat_id']]);

        $db->commit();

        $actor = $is_admin ? "Administrator" : "Student";
        log_audit('reservation_cancelled', "{$actor} cancelled reservation #{$reservation['id']}.", $student_id);

        return ['success' => true, 'message' => 'Reservation cancelled successfully.'];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Cancel reservation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to cancel reservation.'];
    }
}

/**
 * Check In a Student for their Reservation
 */
function check_in_student(int $student_id): array {
    release_expired_reservations_and_sessions();
    $db = get_db_connection();
    $db->beginTransaction();

    try {
        // Find active reservation
        $stmtRes = $db->prepare("
            SELECT * FROM reservations 
            WHERE student_id = ? AND status = 'reserved' 
            FOR UPDATE
        ");
        $stmtRes->execute([$student_id]);
        $reservation = $stmtRes->fetch();

        if (!$reservation) {
            $db->rollBack();
            return ['success' => false, 'message' => 'No active reservation found for check-in.'];
        }

        // Check if expired
        if (strtotime($reservation['reservation_end']) <= time()) {
            $upd = $db->prepare("UPDATE reservations SET status = 'expired' WHERE id = ?");
            $upd->execute([$reservation['id']]);
            $updSeat = $db->prepare("UPDATE seats SET seat_status = 'available' WHERE id = ?");
            $updSeat->execute([$reservation['seat_id']]);
            $db->commit();
            return ['success' => false, 'message' => 'Your reservation has expired and the seat was released.'];
        }

        $settings = get_system_settings();
        $durationMinutes = (int)($settings['session_duration_minutes'] ?? 120);

        // Update reservation status to occupied
        $updRes = $db->prepare("UPDATE reservations SET status = 'occupied' WHERE id = ?");
        $updRes->execute([$reservation['id']]);

        // Update seat status to occupied
        $updSeat = $db->prepare("UPDATE seats SET seat_status = 'occupied' WHERE id = ?");
        $updSeat->execute([$reservation['seat_id']]);

        // Create active session
        $stmtSess = $db->prepare("
            INSERT INTO sessions (student_id, seat_id, reservation_id, check_in_time, expected_end_time, status, created_at)
            VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), 'active', NOW())
        ");
        $stmtSess->execute([$student_id, $reservation['seat_id'], $reservation['id'], $durationMinutes]);
        $sessionId = (int)$db->lastInsertId();

        $db->commit();

        log_audit('student_check_in', "Student checked in (Session #{$sessionId}).", $student_id);

        return [
            'success'          => true,
            'message'          => 'Check-in successful! Your library session has started.',
            'session_id'       => $sessionId,
            'duration_minutes' => $durationMinutes
        ];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Check-in error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Check-in failed. Please try again.'];
    }
}

/**
 * Check Out a Student (Early or Admin Check-out)
 */
function check_out_student(int $student_id, bool $is_admin = false, ?int $session_id = null): array {
    $db = get_db_connection();
    $db->beginTransaction();

    try {
        if ($session_id !== null && $is_admin) {
            $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND status = 'active' FOR UPDATE");
            $stmt->execute([$session_id]);
        } else {
            $stmt = $db->prepare("SELECT * FROM sessions WHERE student_id = ? AND status = 'active' FOR UPDATE");
            $stmt->execute([$student_id]);
        }

        $session = $stmt->fetch();
        if (!$session) {
            $db->rollBack();
            return ['success' => false, 'message' => 'No active library session found to check out.'];
        }

        // Complete session
        $updSess = $db->prepare("UPDATE sessions SET status = 'completed', check_out_time = NOW() WHERE id = ?");
        $updSess->execute([$session['id']]);

        // Complete reservation
        $updRes = $db->prepare("UPDATE reservations SET status = 'completed' WHERE id = ?");
        $updRes->execute([$session['reservation_id']]);

        // Release seat to available
        $updSeat = $db->prepare("UPDATE seats SET seat_status = 'available' WHERE id = ?");
        $updSeat->execute([$session['seat_id']]);

        $db->commit();

        $actor = $is_admin ? "Administrator" : "Student";
        log_audit('student_check_out', "{$actor} checked out session #{$session['id']}.", $session['student_id']);

        return ['success' => true, 'message' => 'Check-out successful! Thank you for using the library.'];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Check-out error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to check out. Please try again.'];
    }
}

