<?php
// Prevent any output before JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'config.php';

try {
    // Clear any previous output
    ob_clean();
    
    $conn = getDBConnection();
    
    $space = isset($_GET['space']) ? trim($_GET['space']) : null;
    $date = isset($_GET['date']) ? trim($_GET['date']) : null;
    $time = isset($_GET['time']) ? trim($_GET['time']) : null;
    
    if (!$space || !$date || !$time) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Space, date, and time are required',
            'received' => [
                'space' => $space,
                'date' => $date,
                'time' => $time
            ]
        ]);
        exit;
    }
    
    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ]);
        exit;
    }
    
    // Validate time format (HH:MM)
    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid time format. Use HH:MM (24-hour format)'
        ]);
        exit;
    }
    
    // Get all tables for this space
    $tables = getTablesForSpace($space);
    
    if (empty($tables)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid space type'
        ]);
        exit;
    }
    
    // Check which tables are available
    $availableTables = [];
    $reservedTables = [];
    
    foreach ($tables as $table) {
        if (isTableAvailable($conn, $space, $table['id'], $date, $time)) {
            $availableTables[] = $table;
        } else {
            $reservedTables[] = $table;
        }
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'available' => $availableTables,
        'reserved' => $reservedTables,
        'query_info' => [
            'space' => $space,
            'date' => $date,
            'time' => $time
        ]
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

ob_end_flush();

function getTablesForSpace($space) {
    $tables = [
        'indoor' => [
            ['id' => 'I1', 'capacity' => 2],
            ['id' => 'I2', 'capacity' => 2],
            ['id' => 'I3', 'capacity' => 4],
            ['id' => 'I4', 'capacity' => 4],
            ['id' => 'I5', 'capacity' => 4],
            ['id' => 'I6', 'capacity' => 3],
            ['id' => 'I7', 'capacity' => 2],
            ['id' => 'I8', 'capacity' => 4]
        ],
        'outdoor' => [
            ['id' => 'O1', 'capacity' => 4],
            ['id' => 'O2', 'capacity' => 4],
            ['id' => 'O3', 'capacity' => 6],
            ['id' => 'O4', 'capacity' => 2],
            ['id' => 'O5', 'capacity' => 4],
            ['id' => 'O6', 'capacity' => 6],
            ['id' => 'O7', 'capacity' => 2],
            ['id' => 'O8', 'capacity' => 4],
            ['id' => 'O9', 'capacity' => 6],
            ['id' => 'O10', 'capacity' => 4]
        ],
        'lounge' => [
            ['id' => 'L1', 'capacity' => 6],
            ['id' => 'L2', 'capacity' => 8],
            ['id' => 'L3', 'capacity' => 4],
            ['id' => 'L4', 'capacity' => 6],
            ['id' => 'L5', 'capacity' => 8],
            ['id' => 'L6', 'capacity' => 4]
        ]
    ];
    
    return isset($tables[$space]) ? $tables[$space] : [];
}

function isTableAvailable($conn, $space, $tableId, $date, $time) {
    // Default reservation duration is 2 hours
    $duration = 2;
    
    // Convert requested time to DateTime for calculations
    $requestedDateTime = new DateTime("$date $time");
    $requestedEndTime = clone $requestedDateTime;
    $requestedEndTime->modify("+$duration hours");
    
    // Format times for SQL
    $requestedTimeStr = $requestedDateTime->format('H:i:s');
    $requestedEndTimeStr = $requestedEndTime->format('H:i:s');
    
    // Check 1: Is table reserved in reservations table?
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE space_type = ? 
        AND table_id = ? 
        AND reservation_date = ? 
        AND status NOT IN ('cancelled', 'completed', 'no_show')
        AND (
            (reservation_time < ? AND 
             DATE_ADD(CONCAT(reservation_date, ' ', reservation_time), INTERVAL COALESCE(duration_hours, 2) HOUR) > ?)
        )
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('sssss', 
        $space, 
        $tableId, 
        $date, 
        $requestedEndTimeStr,
        $requestedTimeStr
    );
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) {
        return false; // Table is reserved
    }
    
    // Check 2: Is table manually blocked by admin?
    $stmt2 = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM table_availability 
        WHERE space_type = ? 
        AND table_id = ? 
        AND block_date = ?
        AND (
            (block_start_time < ? AND block_end_time > ?)
            OR (block_start_time >= ? AND block_start_time < ?)
        )
    ");
    
    if (!$stmt2) {
        error_log("Prepare failed for table_availability: " . $conn->error);
        return true; // If table doesn't exist, assume available
    }
    
    $stmt2->bind_param('sssssss', 
        $space, 
        $tableId, 
        $date, 
        $requestedEndTimeStr,  // Block starts before our reservation ends
        $requestedTimeStr,      // Block ends after our reservation starts
        $requestedTimeStr,      // OR block starts during our reservation
        $requestedEndTimeStr    // Block starts before our reservation ends
    );
    
    if (!$stmt2->execute()) {
        error_log("Execute failed for table_availability: " . $stmt2->error);
        $stmt2->close();
        return true; // Assume available if query fails
    }
    
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $stmt2->close();
    
    if ($row2['count'] > 0) {
        return false; // Table is blocked by admin
    }
    
    return true; // Table is available
}
?>