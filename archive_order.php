<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connection.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['orderId'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    $orderId = $data['orderId'];
    $action = isset($data['action']) ? $data['action'] : 'archive';
    
    // First, get the order details to archive
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
        exit;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if ($action === 'archive') {
        // Option 1: Move to archived_orders table (recommended)
        // Check if archived_orders table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'archived_orders'");
        
        if ($tableCheck->num_rows > 0) {
            // Insert into archived_orders
            $archiveStmt = $conn->prepare("
                INSERT INTO archived_orders (
                    order_id, order_type, status, table_number, customer_name,
                    customer_phone, delivery_address, delivery_notes, items,
                    subtotal, tax, delivery_fee, total, payment_status, 
                    requested_waiter, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $archiveStmt->bind_param(
                'sssssssssddddsis',
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
            
            $archiveStmt->execute();
            $archiveStmt->close();
        }
        
        // Delete from main orders table
        $deleteStmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $deleteStmt->bind_param('s', $orderId);
        
        if ($deleteStmt->execute()) {
            if ($deleteStmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Order archived successfully',
                    'orderId' => $orderId
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Order not found or already archived'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to archive order: ' . $deleteStmt->error
            ]);
        }
        
        $deleteStmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>