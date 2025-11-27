<?php
// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check if config file exists
if (!file_exists('config.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'Config file not found',
        'orders' => []
    ]);
    exit;
}

require_once 'config.php';

try {
    // Get database connection using the function from config.php
    $conn = getDBConnection();
    
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'orders' => []
        ]);
        exit;
    }
    
    // Get filter parameters if provided
    $filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $period = isset($_GET['period']) ? $_GET['period'] : 'all';
    
    // Build query based on filters
    $query = "SELECT * FROM orders WHERE 1=1";
    $params = [];
    $types = '';
    
    // Filter by status
    if ($filter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $filter;
        $types .= 's';
    }
    
    // Filter by type (dinein/delivery)
    if ($type !== 'all') {
        $query .= " AND order_type = ?";
        $params[] = $type;
        $types .= 's';
    }
    
    // Filter by period
    switch($period) {
        case 'today':
            $query .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
            break;
        case 'all':
        default:
            // No additional filter
            break;
    }
    
    // Order by most recent first, and pending orders on top
    $query .= " ORDER BY 
                CASE 
                    WHEN status = 'pending' THEN 1
                    WHEN status = 'preparing' THEN 2
                    WHEN status = 'ready' THEN 3
                    ELSE 4
                END,
                created_at DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare statement: ' . $conn->error,
            'orders' => []
        ]);
        exit;
    }
    
    // Bind parameters if any
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Query execution failed: ' . $stmt->error,
            'orders' => []
        ]);
        exit;
    }
    
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Decode items JSON
        $itemsData = json_decode($row['items'], true);
        if ($itemsData === null) {
            $itemsData = []; // Fallback if JSON decode fails
        }
        
        // Format timestamp for frontend
        $timestamp = $row['created_at'];
        
        // Convert boolean fields
        $requestedWaiter = (bool)$row['requested_waiter'];
        
        // Build order object
        $order = [
            'id' => $row['order_id'],
            'type' => $row['order_type'],
            'status' => $row['status'],
            'tableNumber' => $row['table_number'],
            'customerName' => $row['customer_name'],
            'phone' => $row['customer_phone'],
            'address' => $row['delivery_address'],
            'notes' => $row['delivery_notes'],
            'items' => $itemsData,
            'subtotal' => floatval($row['subtotal']),
            'tax' => floatval($row['tax']),
            'deliveryFee' => floatval($row['delivery_fee']),
            'total' => floatval($row['total']),
            'paymentStatus' => $row['payment_status'],
            'paymentMethod' => isset($row['payment_method']) ? $row['payment_method'] : null,
            'requestedWaiter' => $requestedWaiter,
            'timestamp' => $timestamp
        ];
        
        $orders[] = $order;
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'orders' => []
    ]);
}
?>