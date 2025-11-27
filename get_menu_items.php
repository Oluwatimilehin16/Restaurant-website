<?php
// get_menu_items.php - Retrieve menu items for customers

header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

try {
    // Get filter parameters
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $dietary = isset($_GET['dietary']) ? $_GET['dietary'] : null;
    $day = isset($_GET['day']) ? strtolower($_GET['day']) : null;
    
    // Base query
    $query = "SELECT DISTINCT m.* FROM menu_items m WHERE m.is_active = 1";
    
    // Add category filter
    if ($category) {
        $query .= " AND m.category = ?";
    }
    
    // Add availability filter
    if ($day) {
        $query .= " AND EXISTS (
            SELECT 1 FROM menu_availability ma 
            WHERE ma.menu_item_id = m.id 
            AND ma.day_of_week = ? 
            AND ma.is_available = 1
        )";
    }
    
    // Add dietary filter
    if ($dietary) {
        $query .= " AND EXISTS (
            SELECT 1 FROM menu_dietary md 
            WHERE md.menu_item_id = m.id 
            AND md.dietary_type = ?
        )";
    }
    
    $query .= " ORDER BY m.category, m.item_name";
    
    // Prepare and execute
    $stmt = $conn->prepare($query);
    
    if ($category && $day && $dietary) {
        $stmt->bind_param("sss", $category, $day, $dietary);
    } elseif ($category && $day) {
        $stmt->bind_param("ss", $category, $day);
    } elseif ($category && $dietary) {
        $stmt->bind_param("ss", $category, $dietary);
    } elseif ($day && $dietary) {
        $stmt->bind_param("ss", $day, $dietary);
    } elseif ($category) {
        $stmt->bind_param("s", $category);
    } elseif ($day) {
        $stmt->bind_param("s", $day);
    } elseif ($dietary) {
        $stmt->bind_param("s", $dietary);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $menuItems = [];
    
    while ($row = $result->fetch_assoc()) {
        $itemId = $row['id'];
        
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
        
        // Build menu item object with both description fields
        $menuItems[] = [
            'id' => $row['id'],
            'itemName' => $row['item_name'],
            'category' => $row['category'],
            'shortDescription' => $row['short_description'],
            'fullDescription' => $row['full_description'],
            'basePrice' => floatval($row['base_price']),
            'prepTime' => $row['prep_time'],
            'servingSize' => $row['serving_size'],
            'imagePath' => $row['image_path'],
            'availability' => $availability,
            'dietary' => $dietary,
            'specialFeatures' => $specialFeatures,
            'customizations' => $customizations
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'items' => $menuItems,
        'count' => count($menuItems)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching menu items: ' . $e->getMessage()
    ]);
}

$conn->close();
?>