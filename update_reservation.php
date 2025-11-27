<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
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
    
    $reservationId = isset($data['reservationId']) ? $data['reservationId'] : null;
    $action = isset($data['action']) ? $data['action'] : 'update_status';
    
    if (!$reservationId) {
        echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
        exit;
    }
    
    switch ($action) {
        case 'update_status':
            $newStatus = isset($data['status']) ? $data['status'] : null;
            
            if (!$newStatus) {
                echo json_encode(['success' => false, 'message' => 'Status is required']);
                exit;
            }
            
            $validStatuses = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];
            if (!in_array($newStatus, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE reservation_id = ?");
            $stmt->bind_param('ss', $newStatus, $reservationId);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Reservation status updated successfully',
                        'reservationId' => $reservationId,
                        'newStatus' => $newStatus
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Reservation not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
            }
            $stmt->close();
            break;
            
        case 'update_payment':
            $paymentStatus = isset($data['paymentStatus']) ? $data['paymentStatus'] : null;
            $paymentMethod = isset($data['paymentMethod']) ? $data['paymentMethod'] : null;
            
            if (!$paymentStatus) {
                echo json_encode(['success' => false, 'message' => 'Payment status is required']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE reservations SET payment_status = ?, payment_method = ?, updated_at = CURRENT_TIMESTAMP WHERE reservation_id = ?");
            $stmt->bind_param('sss', $paymentStatus, $paymentMethod, $reservationId);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Payment status updated successfully',
                        'reservationId' => $reservationId
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Reservation not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
            }
            $stmt->close();
            break;
            
        case 'cancel':
            $reason = isset($data['reason']) ? $data['reason'] : 'Cancelled by admin';
            
            $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE reservation_id = ?");
            $stmt->bind_param('s', $reservationId);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Reservation cancelled successfully',
                        'reservationId' => $reservationId
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Reservation not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel: ' . $stmt->error]);
            }
            $stmt->close();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>