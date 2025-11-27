<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!file_exists('config.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'Config file not found'
    ]);
    exit;
}

require_once 'config.php';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['orderId']) || !isset($data['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: orderId and status'
    ]);
    exit;
}

$orderId = $data['orderId'];
$newStatus = $data['status'];
$paymentMethod = isset($data['paymentMethod']) ? $data['paymentMethod'] : null;
$paymentStatus = isset($data['paymentStatus']) ? $data['paymentStatus'] : null;

// Validate status
$validStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit;
}

// Validate payment method if provided
$validPaymentMethods = ['cash', 'card', 'transfer'];
if ($paymentMethod && !in_array($paymentMethod, $validPaymentMethods)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment method'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    
    // Check if payment fields exist in table, if not add them
    $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    if ($checkColumns->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) DEFAULT NULL");
        $conn->query("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) DEFAULT 'unpaid'");
    }
    
    // Update the order status and payment info if provided
    if ($paymentMethod && $paymentStatus) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_method = ?, payment_status = ? WHERE order_id = ?");
        
        if (!$stmt) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to prepare statement: ' . $conn->error
            ]);
            exit;
        }
        
        $stmt->bind_param('ssss', $newStatus, $paymentMethod, $paymentStatus, $orderId);
    } else {
        // Just update status
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        
        if (!$stmt) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to prepare statement: ' . $conn->error
            ]);
            exit;
        }
        
        $stmt->bind_param('ss', $newStatus, $orderId);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response = [
                'success' => true,
                'message' => 'Order status updated successfully',
                'orderId' => $orderId,
                'newStatus' => $newStatus
            ];
            
            if ($paymentMethod) {
                $response['paymentMethod'] = $paymentMethod;
                $response['paymentStatus'] = $paymentStatus;
            }
            
            echo json_encode($response);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Order not found or status unchanged'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update order: ' . $stmt->error
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
?>