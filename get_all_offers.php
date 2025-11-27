<?php
// get_all_offers.php - Retrieve all special offers for admin management

header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Query to get all offers (active and inactive)
    $query = "
        SELECT 
            id,
            title,
            description,
            image_path,
            original_price,
            discounted_price,
            discount_percentage,
            valid_from,
            valid_until,
            badge,
            is_active,
            display_order
        FROM special_offers 
        ORDER BY display_order ASC, created_at DESC
    ";
    
    $result = $conn->query($query);
    
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
            'validFrom' => $row['valid_from'],
            'validUntil' => $row['valid_until'],
            'badge' => $row['badge'],
            'isActive' => (bool)$row['is_active'],
            'displayOrder' => intval($row['display_order'])
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'offers' => $offers,
        'count' => count($offers)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching offers: ' . $e->getMessage(),
        'offers' => []
    ]);
}
?>