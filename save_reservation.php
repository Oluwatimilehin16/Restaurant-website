<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Validate required fields
    $required = ['space', 'tableId', 'tableCapacity', 'date', 'time', 'fullName', 'phone', 'email'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }
    
    $space = $data['space'];
    $tableId = $data['tableId'];
    $tableCapacity = intval($data['tableCapacity']);
    $date = $data['date'];
    $time = $data['time'];
    $fullName = $data['fullName'];
    $phone = $data['phone'];
    $email = $data['email'];
    $depositAmount = isset($data['deposit']) ? floatval($data['deposit']) : 5000;
    $bookingSource = isset($data['bookingSource']) ? $data['bookingSource'] : 'online';
    
    // Double-check availability before saving
    if (!isTableAvailable($conn, $space, $tableId, $date, $time)) {
        echo json_encode([
            'success' => false,
            'message' => 'Sorry, this table is no longer available. Please select another time or table.'
        ]);
        exit;
    }
    
    // Generate unique reservation ID
    $resIdPrefix = 'RES-' . date('Ymd') . '-';
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE reservation_id LIKE ?");
    $searchPattern = $resIdPrefix . '%';
    $stmt->bind_param('s', $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $resNumber = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
    $reservationId = $resIdPrefix . $resNumber;
    $stmt->close();
    
    // Insert reservation
    $stmt = $conn->prepare("
        INSERT INTO reservations (
            reservation_id, space_type, table_id, table_capacity,
            reservation_date, reservation_time, duration_hours,
            customer_name, customer_phone, customer_email,
            deposit_amount, payment_status, status, booking_source
        ) VALUES (?, ?, ?, ?, ?, ?, 2, ?, ?, ?, ?, 'pending', 'confirmed', ?)
    ");
    
    $paymentStatus = 'pending';
    
    $stmt->bind_param(
        'sssississds',
        $reservationId,
        $space,
        $tableId,
        $tableCapacity,
        $date,
        $time,
        $fullName,
        $phone,
        $email,
        $depositAmount,
        $bookingSource
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Reservation saved successfully',
            'reservationId' => $reservationId,
            'data' => [
                'space' => $space,
                'table' => $tableId,
                'date' => $date,
                'time' => $time,
                'name' => $fullName
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save reservation: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function isTableAvailable($conn, $space, $tableId, $date, $time) {
    $checkStartTime = date('H:i:s', strtotime($time) - (60 * 60));
    $checkEndTime = date('H:i:s', strtotime($time) + (2 * 60 * 60));
    
    // Check reservations
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE space_type = ? 
        AND table_id = ? 
        AND reservation_date = ? 
        AND status NOT IN ('cancelled', 'completed', 'no_show')
        AND (
            (reservation_time BETWEEN ? AND ?)
            OR (DATE_ADD(CONCAT(reservation_date, ' ', reservation_time), INTERVAL duration_hours HOUR) BETWEEN ? AND ?)
        )
    ");
    
    $stmt->bind_param('sssssss', 
        $space, $tableId, $date, 
        $checkStartTime, $checkEndTime,
        $checkStartTime, $checkEndTime
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) return false;
    
    // Check admin blocks
    $stmt2 = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM table_availability 
        WHERE space_type = ? 
        AND table_id = ? 
        AND block_date = ?
        AND ? BETWEEN block_start_time AND block_end_time
    ");
    
    $stmt2->bind_param('ssss', $space, $tableId, $date, $time);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $stmt2->close();
    
    return $row2['count'] == 0;
}
?>