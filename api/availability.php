<?php
/**
 * API: Seat Availability Count Endpoint
 * Method: GET
 * Returns: JSON with live total, available, reserved, and occupied counts
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // Dynamically calculate seat availability and release any expired items
    $counts = get_seat_counts();

    echo json_encode([
        'success'   => true,
        'counts'    => $counts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to calculate seat availability.'
    ]);
}

