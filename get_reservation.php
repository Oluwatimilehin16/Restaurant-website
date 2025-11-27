<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Get filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $date = isset($_GET['date']) ? $_GET['date'] : 'all';
    $space = isset($_GET['space']) ? $_GET['space'] : 'all';
    
    // Build query
    $query = "SELECT * FROM reservations WHERE 1=1";
    $params = [];
    $types = '';
    
    // Filter by status
    if ($status !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Filter by date
    if ($date === 'today') {
        $query .= " AND reservation_date = CURDATE()";
    } elseif ($date === 'upcoming') {
        $query .= " AND reservation_date >= CURDATE()";
    } elseif ($date === 'past') {
        $query .= " AND reservation_date < CURDATE()";
    } elseif ($date !== 'all') {
        $query .= " AND reservation_date = ?";
        $params[] = $date;
        $types .= 's';
    }
    
    // Filter by space
    if ($space !== 'all') {
        $query .= " AND space_type = ?";
        $params[] = $space;
        $types .= 's';
    }
    
    // Order by date and time
    $query .= " ORDER BY 
                CASE 
                    WHEN status = 'confirmed' THEN 1
                    WHEN status = 'pending' THEN 2
                    WHEN status = 'seated' THEN 3
                    ELSE 4
                END,
                reservation_date DESC,
                reservation_time DESC";
    
    $stmt = $conn->prepare($query);
    
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservation = [
            'id' => $row['reservation_id'],
            'spaceType' => $row['space_type'],
            'tableId' => $row['table_id'],
            'tableCapacity' => intval($row['table_capacity']),
            'date' => $row['reservation_date'],
            'time' => $row['reservation_time'],
            'duration' => intval($row['duration_hours']),
            'customerName' => $row['customer_name'],
            'customerPhone' => $row['customer_phone'],
            'customerEmail' => $row['customer_email'],
            'depositAmount' => floatval($row['deposit_amount']),
            'paymentStatus' => $row['payment_status'],
            'paymentMethod' => $row['payment_method'],
            'status' => $row['status'],
            'bookingSource' => $row['booking_source'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at']
        ];
        
        $reservations[] = $reservation;
    }
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations,
        'count' => count($reservations)
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'reservations' => []
    ]);
}
?>