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
    
    $spaceType = isset($data['spaceType']) ? $data['spaceType'] : null;
    $tableId = isset($data['tableId']) ? $data['tableId'] : null;
    $blockDate = isset($data['blockDate']) ? $data['blockDate'] : null;
    $blockStartTime = isset($data['blockStartTime']) ? $data['blockStartTime'] : null;
    $blockEndTime = isset($data['blockEndTime']) ? $data['blockEndTime'] : null;
    $reason = isset($data['reason']) ? $data['reason'] : 'Blocked by admin';
    
    // Validate required fields
    if (!$spaceType || !$tableId || !$blockDate || !$blockStartTime || !$blockEndTime) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit;
    }
    
    // Check if table_availability table exists, if not create it
    $checkTable = "SHOW TABLES LIKE 'table_availability'";
    $result = $conn->query($checkTable);
    
    if ($result->num_rows === 0) {
        $createTable = "CREATE TABLE table_availability (
            id INT AUTO_INCREMENT PRIMARY KEY,
            space_type VARCHAR(50) NOT NULL,
            table_id VARCHAR(10) NOT NULL,
            block_date DATE NOT NULL,
            block_start_time TIME NOT NULL,
            block_end_time TIME NOT NULL,
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_space_table (space_type, table_id),
            INDEX idx_block_date (block_date)
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception('Failed to create table_availability table');
        }
    }
    
    // Insert block record
    $stmt = $conn->prepare("
        INSERT INTO table_availability 
        (space_type, table_id, block_date, block_start_time, block_end_time, reason) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('ssssss', 
        $spaceType, 
        $tableId, 
        $blockDate, 
        $blockStartTime, 
        $blockEndTime, 
        $reason
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Table blocked successfully',
            'blockId' => $stmt->insert_id
        ]);
    } else {
        throw new Exception('Failed to block table: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>