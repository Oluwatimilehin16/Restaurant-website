<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (!isset($data['orderId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required field: orderId']);
    exit;
}

$orderId = $data['orderId'];
$action = isset($data['action']) ? $data['action'] : 'archive'; // 'archive' or 'delete'

try {
    // Check if order exists
    $checkStmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $checkStmt->bind_param('s', $orderId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($action === 'archive') {
        // Archive the order (move to archived_orders table)
        $archiveStmt = $conn->prepare("
            INSERT INTO archived_orders (
                order_id, order_type, status, table_number, customer_name,
                customer_phone, delivery_address, delivery_notes, items,
                subtotal, tax, delivery_fee, total, payment_status,
                requested_waiter, created_at, updated_at, archived_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $archiveStmt->bind_param(
            'sssssssssddddisss',
            $order['order_id'],
            $order['order_type'],
            $order['status'],
            $order['table_number'],
            $order['customer_name'],
            $order['customer_phone'],
            $order['delivery_address'],
            $order['delivery_notes'],
            $order['items'],
            $order['subtotal'],
            $order['tax'],
            $order['delivery_fee'],
            $order['total'],
            $order['payment_status'],
            $order['requested_waiter'],
            $order['created_at'],
            $order['updated_at']
        );
        
        if (!$archiveStmt->execute()) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to archive order: ' . $archiveStmt->error
            ]);
            exit;
        }
        
        $archiveStmt->close();
    }
    
    // Delete from orders table
    $deleteStmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
    $deleteStmt->bind_param('s', $orderId);
    
    if ($deleteStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $action === 'archive' ? 'Order archived successfully' : 'Order deleted successfully',
            'orderId' => $orderId,
            'action' => $action
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete order: ' . $deleteStmt->error
        ]);
    }
    
    $deleteStmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>