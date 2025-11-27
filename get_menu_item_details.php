<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    if (empty($_GET['id'])) {
        throw new Exception("Menu item ID is required");
    }
    
    $conn = getDBConnection();
    $itemId = intval($_GET['id']);
    
    // Get menu item basic info
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        throw new Exception("Menu item not found");
    }
    
    // Get availability
    $stmt = $conn->prepare("SELECT day_of_week FROM menu_availability WHERE menu_item_id = ? AND is_available = 1");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $availability = [];
    while ($row = $result->fetch_assoc()) {
        $availability[] = $row['day_of_week'];
    }
    $stmt->close();
    
    // Get dietary info
    $stmt = $conn->prepare("SELECT dietary_type FROM menu_dietary WHERE menu_item_id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dietary = [];
    while ($row = $result->fetch_assoc()) {
        $dietary[] = $row['dietary_type'];
    }
    $stmt->close();
    
    // Get special features
    $stmt = $conn->prepare("SELECT feature_text FROM special_features WHERE menu_item_id = ? ORDER BY display_order");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $specialFeatures = [];
    while ($row = $result->fetch_assoc()) {
        $specialFeatures[] = $row['feature_text'];
    }
    $stmt->close();
    
    // Get customization groups and options
    $stmt = $conn->prepare("
        SELECT id, group_name, is_required, allow_multiple, display_order 
        FROM customization_groups 
        WHERE menu_item_id = ? 
        ORDER BY display_order
    ");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $customizations = [];
    while ($customRow = $result->fetch_assoc()) {
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
    $stmt->close();
    
    // Build complete item object
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
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>