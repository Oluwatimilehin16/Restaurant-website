<?php
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception("Invalid JSON data received");
    }
    
    if (empty($data['id'])) {
        throw new Exception("Menu item ID is required");
    }

    $conn = getDBConnection();
    $menuItemId = intval($data['id']);
    
    $conn->begin_transaction();

    // Handle image update
    $imagePath = $data['existingImagePath'];
    if (!empty($data['imageData'])) {
        // New image uploaded
        $imageData = $data['imageData'];
        
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
            $imageType = strtolower($matches[1]);
            $imageBase64 = $matches[2];
            
            $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
            if (!in_array($imageType, $allowedTypes)) {
                throw new Exception("Invalid image type");
            }
            
            $imageContent = base64_decode($imageBase64);
            if ($imageContent === false) {
                throw new Exception("Failed to decode image data");
            }
            
            $maxSize = 5 * 1024 * 1024;
            if (strlen($imageContent) > $maxSize) {
                throw new Exception("Image file too large. Maximum size is 5MB");
            }
            
            $fileName = time() . "_menu_item." . $imageType;
            
            if (!is_dir(UPLOAD_DIR)) {
                if (!mkdir(UPLOAD_DIR, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            $destPath = UPLOAD_DIR . $fileName;
            
            if (!file_put_contents($destPath, $imageContent)) {
                throw new Exception("Failed to save image file");
            }
            
            // Delete old image if exists
            if ($imagePath && file_exists($imagePath)) {
                unlink($imagePath);
            }
            
            $imagePath = 'uploads/menu_items/' . $fileName;
        }
    }

    // Validate required fields
    if (empty($data['itemName'])) {
        throw new Exception("Item name is required");
    }
    if (empty($data['category'])) {
        throw new Exception("Category is required");
    }
    if (empty($data['basePrice']) || $data['basePrice'] <= 0) {
        throw new Exception("Valid base price is required");
    }

    // Prepare data
    $shortDescription = !empty($data['shortDescription']) ? $data['shortDescription'] : null;
    $fullDescription  = !empty($data['fullDescription']) ? $data['fullDescription'] : null;
    $prepTime         = !empty($data['prepTime']) ? intval($data['prepTime']) : null;
    $servingSize      = !empty($data['servingSize']) ? $data['servingSize'] : null;

    // Update menu_items
    $stmt = $conn->prepare("
        UPDATE menu_items 
        SET item_name = ?, 
            category = ?, 
            short_description = ?, 
            full_description = ?, 
            base_price = ?, 
            prep_time = ?, 
            serving_size = ?, 
            image_path = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "ssssdissi",
        $data['itemName'],
        $data['category'],
        $shortDescription,
        $fullDescription,
        $data['basePrice'],
        $prepTime,
        $servingSize,
        $imagePath,
        $menuItemId
    );
    
    $stmt->execute();
    $stmt->close();

    // Delete existing related data
    $conn->query("DELETE FROM menu_availability WHERE menu_item_id = $menuItemId");
    $conn->query("DELETE FROM menu_dietary WHERE menu_item_id = $menuItemId");
    $conn->query("DELETE FROM special_features WHERE menu_item_id = $menuItemId");
    
    // Delete customization options first
    $conn->query("DELETE co FROM customization_options co 
                  INNER JOIN customization_groups cg ON co.group_id = cg.id 
                  WHERE cg.menu_item_id = $menuItemId");
    
    // Delete customization groups
    $conn->query("DELETE FROM customization_groups WHERE menu_item_id = $menuItemId");

    // Insert availability with duplicate prevention
    if (!empty($data['availability']) && is_array($data['availability'])) {
        $uniqueDays = array_unique($data['availability']);
        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        $availStmt = $conn->prepare("
            INSERT INTO menu_availability (menu_item_id, day_of_week, is_available) 
            VALUES (?, ?, 1)
        ");
        
        foreach ($uniqueDays as $day) {
            if (in_array(strtolower($day), $validDays)) {
                $dayLower = strtolower($day);
                $availStmt->bind_param("is", $menuItemId, $dayLower);
                try {
                    $availStmt->execute();
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() != 1062) {
                        throw $e;
                    }
                }
            }
        }
        $availStmt->close();
    }

    // Insert dietary info with duplicate prevention
    if (!empty($data['dietary']) && is_array($data['dietary'])) {
        $uniqueDietary = array_unique($data['dietary']);
        $validDietary = ['vegetarian', 'vegan', 'glutenFree', 'dairyFree', 'nutFree', 'spicy'];
        
        $dietStmt = $conn->prepare("
            INSERT INTO menu_dietary (menu_item_id, dietary_type) 
            VALUES (?, ?)
        ");
        
        foreach ($uniqueDietary as $dietType) {
            if (in_array($dietType, $validDietary)) {
                $dietStmt->bind_param("is", $menuItemId, $dietType);
                try {
                    $dietStmt->execute();
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() != 1062) {
                        error_log("Dietary insert error for $dietType: " . $e->getMessage());
                        throw new Exception("Failed to save dietary information: " . $dietType);
                    }
                }
            }
        }
        $dietStmt->close();
    }

    // Insert special features
    if (!empty($data['specialFeatures']) && is_array($data['specialFeatures'])) {
        $uniqueFeatures = array_unique(array_filter($data['specialFeatures'], function($f) {
            return !empty(trim($f));
        }));
        
        $featureStmt = $conn->prepare("
            INSERT INTO special_features (menu_item_id, feature_text, display_order) 
            VALUES (?, ?, ?)
        ");
        
        $displayOrder = 0;
        foreach ($uniqueFeatures as $feature) {
            $featureStmt->bind_param("isi", $menuItemId, $feature, $displayOrder);
            $featureStmt->execute();
            $displayOrder++;
        }
        $featureStmt->close();
    }

    // Insert customization groups & options
    if (!empty($data['customizations']) && is_array($data['customizations'])) {
        $groupStmt = $conn->prepare("
            INSERT INTO customization_groups (menu_item_id, group_name, is_required, allow_multiple, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $optionStmt = $conn->prepare("
            INSERT INTO customization_options (group_id, option_name, additional_price, is_default, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($data['customizations'] as $gIndex => $group) {
            // Skip empty group names
            if (empty($group['groupName']) || empty($group['options'])) {
                continue;
            }
            
            $isRequired = !empty($group['isRequired']) ? 1 : 0;
            $allowMultiple = !empty($group['allowMultiple']) ? 1 : 0;
            
            $groupStmt->bind_param(
                "isiii",
                $menuItemId,
                $group['groupName'],
                $isRequired,
                $allowMultiple,
                $gIndex
            );
            $groupStmt->execute();
            $groupId = $groupStmt->insert_id;

            // Insert options for this group
            if (!empty($group['options']) && is_array($group['options'])) {
                // If required and not multiple, ensure exactly one default
                if ($isRequired && !$allowMultiple) {
                    $hasDefault = false;
                    foreach ($group['options'] as $option) {
                        if (!empty($option['isDefault'])) {
                            $hasDefault = true;
                            break;
                        }
                    }
                    // If no default, set first option as default
                    if (!$hasDefault && count($group['options']) > 0) {
                        $group['options'][0]['isDefault'] = true;
                    }
                }
                
                foreach ($group['options'] as $oIndex => $option) {
                    // Skip empty option names
                    if (empty($option['optionName'])) {
                        continue;
                    }
                    
                    $additionalPrice = isset($option['additionalPrice']) ? floatval($option['additionalPrice']) : 0;
                    $isDefault = !empty($option['isDefault']) ? 1 : 0;
                    
                    $optionStmt->bind_param(
                        "isdii",
                        $groupId,
                        $option['optionName'],
                        $additionalPrice,
                        $isDefault,
                        $oIndex
                    );
                    $optionStmt->execute();
                }
            }
        }
        
        $groupStmt->close();
        $optionStmt->close();
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Menu item updated successfully!",
        "menuItemId" => $menuItemId,
        "imagePath" => $imagePath
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Menu item update error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
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