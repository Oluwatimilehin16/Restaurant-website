<?php
// get_menu_item.php - Retrieve single menu item details

header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

try {
    // Get item ID from query parameter
    $itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
        exit;
    }
    
    // Get menu item basic info
    $stmt = $conn->prepare("
        SELECT * FROM menu_items 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $item = $result->fetch_assoc();
    $stmt->close();
    
    // Get availability
    $availStmt = $conn->prepare("
        SELECT day_of_week FROM menu_availability 
        WHERE menu_item_id = ? AND is_available = 1
    ");
    $availStmt->bind_param("i", $itemId);
    $availStmt->execute();
    $availResult = $availStmt->get_result();
    
    $availability = [];
    while ($availRow = $availResult->fetch_assoc()) {
        $availability[] = $availRow['day_of_week'];
    }
    $availStmt->close();
    
    // Get dietary information
    $dietStmt = $conn->prepare("
        SELECT dietary_type FROM menu_dietary WHERE menu_item_id = ?
    ");
    $dietStmt->bind_param("i", $itemId);
    $dietStmt->execute();
    $dietResult = $dietStmt->get_result();
    
    $dietary = [];
    while ($dietRow = $dietResult->fetch_assoc()) {
        $dietary[] = $dietRow['dietary_type'];
    }
    $dietStmt->close();
    
    // Get special features
    $featureStmt = $conn->prepare("
        SELECT feature_text FROM special_features 
        WHERE menu_item_id = ? 
        ORDER BY display_order
    ");
    $featureStmt->bind_param("i", $itemId);
    $featureStmt->execute();
    $featureResult = $featureStmt->get_result();
    
    $specialFeatures = [];
    while ($featureRow = $featureResult->fetch_assoc()) {
        $specialFeatures[] = $featureRow['feature_text'];
    }
    $featureStmt->close();
    
    // Get customization groups and options
    $customStmt = $conn->prepare("
        SELECT id, group_name, is_required, allow_multiple, display_order 
        FROM customization_groups 
        WHERE menu_item_id = ? 
        ORDER BY display_order
    ");
    $customStmt->bind_param("i", $itemId);
    $customStmt->execute();
    $customResult = $customStmt->get_result();
    
    $customizations = [];
    while ($customRow = $customResult->fetch_assoc()) {
        $groupId = $customRow['id'];
        
        // Get options for this group
        $optStmt = $conn->prepare("
            SELECT option_name, additional_price, is_default, display_order 
            FROM customization_options 
            WHERE group_id = ? 
            ORDER BY display_order
        ");
        $optStmt->bind_param("i", $groupId);
        $optStmt->execute();
        $optResult = $optStmt->get_result();
        
        $options = [];
        while ($optRow = $optResult->fetch_assoc()) {
            $options[] = [
                'name' => $optRow['option_name'],
                'price' => floatval($optRow['additional_price']),
                'isDefault' => (bool)$optRow['is_default']
            ];
        }
        $optStmt->close();
        
        $customizations[] = [
            'name' => $customRow['group_name'],
            'required' => (bool)$customRow['is_required'],
            'multiple' => (bool)$customRow['allow_multiple'],
            'options' => $options
        ];
    }
    $customStmt->close();
    
    // Build complete menu item object
    $menuItem = [
        'id' => $item['id'],
        'itemName' => $item['item_name'],
        'category' => $item['category'],
        'shortDescription' => $item['short_description'],
        'fullDescription' => $item['full_description'],
        'basePrice' => floatval($item['base_price']),
        'prepTime' => $item['prep_time'],
        'servingSize' => $item['serving_size'],
        'imagePath' => $item['image_path'],
        'availability' => $availability,
        'dietary' => $dietary,
        'specialFeatures' => $specialFeatures,
        'customizations' => $customizations
    ];
    
    echo json_encode([
        'success' => true,
        'item' => $menuItem
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching menu item: ' . $e->getMessage()
    ]);
}

$conn->close();
?>