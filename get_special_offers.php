<?php
// get_special_offers.php - Retrieve active special offers
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Get current date
    $today = date('Y-m-d');
    
    // Query to get active offers that are currently valid
    $query = "
        SELECT 
            id,
            title,
            description,
            image_path,
            original_price,
            discounted_price,
            discount_percentage,
            valid_until,
            badge
        FROM special_offers 
        WHERE is_active = 1 
        AND valid_from <= ? 
        AND valid_until >= ?
        ORDER BY display_order ASC, created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $offers = [];
    
    while ($row = $result->fetch_assoc()) {
        $offers[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'image' => $row['image_path'],
            'originalPrice' => floatval($row['original_price']),
            'discountedPrice' => floatval($row['discounted_price']),
            'discountPercentage' => intval($row['discount_percentage']),
            'validUntil' => $row['valid_until'],
            'badge' => $row['badge']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'offers' => $offers,
        'count' => count($offers)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching special offers: ' . $e->getMessage(),
        'offers' => []
    ]);
}
?>