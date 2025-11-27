<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    $conn = getDBConnection();
    
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $spaceType = isset($_GET['space']) ? $_GET['space'] : null;
    
    $sql = "SELECT * FROM table_availability WHERE block_date = ?";
    $params = [$date];
    $types = "s";
    
    if ($spaceType) {
        $sql .= " AND space_type = ?";
        $params[] = $spaceType;
        $types .= "s";
    }
    
    $sql .= " ORDER BY block_start_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $blockedTables = [];
    while ($row = $result->fetch_assoc()) {
        $blockedTables[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'blockedTables' => $blockedTables
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>