<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (empty($data['id'])) {
        throw new Exception("Menu item ID is required");
    }
    
    $conn = getDBConnection();
    $menuItemId = intval($data['id']);
    
    // Get image path before deleting
    $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $menuItemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        throw new Exception("Menu item not found");
    }
    
    $conn->begin_transaction();
    
    // Delete related records (foreign keys should handle this with ON DELETE CASCADE)
    $conn->query("DELETE FROM menu_availability WHERE menu_item_id = $menuItemId");
    $conn->query("DELETE FROM menu_dietary WHERE menu_item_id = $menuItemId");
    $conn->query("DELETE FROM special_features WHERE menu_item_id = $menuItemId");
    
    // Delete customization options first
    $conn->query("DELETE co FROM customization_options co 
                  INNER JOIN customization_groups cg ON co.group_id = cg.id 
                  WHERE cg.menu_item_id = $menuItemId");
    
    // Delete customization groups
    $conn->query("DELETE FROM customization_groups WHERE menu_item_id = $menuItemId");
    
    // Delete menu item
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $menuItemId);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    // Delete image file
    if ($item['image_path'] && file_exists($item['image_path'])) {
        unlink($item['image_path']);
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Menu item deleted successfully"
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>