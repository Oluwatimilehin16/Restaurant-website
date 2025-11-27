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

if (!$data || !isset($data['blockId'])) {
    echo json_encode(['success' => false, 'message' => 'Block ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM table_availability WHERE id = ?");
    $stmt->bind_param('i', $data['blockId']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Table unblocked successfully'
        ]);
    } else {
        throw new Exception('Failed to unblock table');
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