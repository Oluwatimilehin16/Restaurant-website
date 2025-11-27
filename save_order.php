<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if config file exists
if (!file_exists('config.php')) {
    echo json_encode(['success' => false, 'message' => 'Config file not found', 'debug' => 'config.php missing']);
    exit;
}

require_once 'config.php';

// Get database connection
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Debug: Log what we received
$debugInfo = [
    'received_data' => $data,
    'raw_input' => $input
];

if (!$data) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON data',
        'debug' => $debugInfo
    ]);
    exit;
}

try {
    // Generate unique order ID
    $orderIdPrefix = 'ORD-' . date('Ymd') . '-';
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_id LIKE ?");
    $searchPattern = $orderIdPrefix . '%';
    $stmt->bind_param('s', $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $orderNumber = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
    $orderId = $orderIdPrefix . $orderNumber;
    $stmt->close();
    
    // Prepare order data with validation
    $orderType = isset($data['orderType']) ? $data['orderType'] : null;
    $status = 'pending';
    $tableNumber = isset($data['tableNumber']) ? intval($data['tableNumber']) : null;
    $customerName = isset($data['customerName']) ? $data['customerName'] : null;
    $customerPhone = isset($data['customerPhone']) ? $data['customerPhone'] : null;
    $deliveryAddress = isset($data['deliveryAddress']) ? $data['deliveryAddress'] : null;
    $deliveryNotes = isset($data['deliveryNotes']) ? $data['deliveryNotes'] : null;
    $items = isset($data['items']) ? json_encode($data['items']) : '[]';
    $subtotal = isset($data['subtotal']) ? floatval($data['subtotal']) : 0;
    $tax = isset($data['tax']) ? floatval($data['tax']) : 0;
    $deliveryFee = isset($data['deliveryFee']) ? floatval($data['deliveryFee']) : 0;
    $total = isset($data['total']) ? floatval($data['total']) : 0;
    $paymentStatus = isset($data['paymentStatus']) ? $data['paymentStatus'] : 'unpaid';
    $requestedWaiter = isset($data['requestedWaiter']) ? ($data['requestedWaiter'] ? 1 : 0) : 0;
    
    // Debug: Show what we're about to insert
    $debugInfo['prepared_data'] = [
        'orderId' => $orderId,
        'orderType' => $orderType,
        'status' => $status,
        'tableNumber' => $tableNumber,
        'customerName' => $customerName,
        'customerPhone' => $customerPhone,
        'deliveryAddress' => $deliveryAddress,
        'deliveryNotes' => $deliveryNotes,
        'items' => $items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'deliveryFee' => $deliveryFee,
        'total' => $total,
        'paymentStatus' => $paymentStatus,
        'requestedWaiter' => $requestedWaiter
    ];
    
    // Validate required fields
    if (empty($orderType)) {
        echo json_encode([
            'success' => false,
            'message' => 'Order type is required',
            'debug' => $debugInfo
        ]);
        exit;
    }
    
    if ($orderType !== 'dinein' && $orderType !== 'delivery') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order type: ' . $orderType . '. Must be "dinein" or "delivery"',
            'debug' => $debugInfo
        ]);
        exit;
    }
    
    // Insert order into database
    $stmt = $conn->prepare("
        INSERT INTO orders (
            order_id, order_type, status, table_number, customer_name, 
            customer_phone, delivery_address, delivery_notes, items, 
            subtotal, tax, delivery_fee, total, payment_status, requested_waiter
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare statement: ' . $conn->error,
            'debug' => $debugInfo
        ]);
        exit;
    }
    
    // Bind parameters
    $stmt->bind_param(
        'sssisssssddddsi',
        $orderId,
        $orderType,
        $status,
        $tableNumber,
        $customerName,
        $customerPhone,
        $deliveryAddress,
        $deliveryNotes,
        $items,
        $subtotal,
        $tax,
        $deliveryFee,
        $total,
        $paymentStatus,
        $requestedWaiter
    );
    
    if ($stmt->execute()) {
        // Verify the order was actually inserted
        $verifyStmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $verifyStmt->bind_param('s', $orderId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        $insertedOrder = $verifyResult->fetch_assoc();
        $verifyStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order saved successfully',
            'orderId' => $orderId,
            'debug' => [
                'inserted_data' => $debugInfo['prepared_data'],
                'verified_in_db' => $insertedOrder ? true : false,
                'db_record' => $insertedOrder
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save order: ' . $stmt->error,
            'debug' => $debugInfo
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => isset($debugInfo) ? $debugInfo : []
    ]);
}
?>