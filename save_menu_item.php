<?php
require_once 'config.php';
require_once 'cloudinary_config.php'; // Add this line

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

try {
    // Read JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception("Invalid JSON data received");
    }

    $conn = getDBConnection();
    $conn->begin_transaction();

    // Handle base64 image data and upload to Cloudinary
    $imageUrl = null;
    if (!empty($data['imageData'])) {
        $imageData = $data['imageData'];
        
        // Extract base64 data and image type
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
            $imageType = strtolower($matches[1]);
            $imageBase64 = $matches[2];
            
            // Validate image type
            $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
            if (!in_array($imageType, $allowedTypes)) {
                throw new Exception("Invalid image type: $imageType. Allowed types: " . implode(', ', $allowedTypes));
            }
            
            // Decode base64
            $imageContent = base64_decode($imageBase64);
            if ($imageContent === false) {
                throw new Exception("Failed to decode image data");
            }
            
            // Check file size (limit to 5MB)
            $maxSize = 5 * 1024 * 1024; // 5MB
            if (strlen($imageContent) > $maxSize) {
                throw new Exception("Image file too large. Maximum size is 5MB");
            }
            
$cld = cloudinary();
$publicId = 'menu_items/' . time() . '_menu_item';

try {
    $uploadResponse = $cld->uploadApi()->upload(
        "data:image/$imageType;base64,$imageBase64",
        [
            'public_id' => $publicId,
            'folder' => 'menu_items',
            'overwrite' => true,
            'resource_type' => 'image'
        ]
    );

    $imageUrl = $uploadResponse['secure_url'];

} catch (Exception $uploadError) {
    throw new Exception("Cloudinary upload failed: " . $uploadError->getMessage());
}

            
        } else {
            throw new Exception("Invalid image data format");
        }
    } else {
        throw new Exception("No image provided");
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

    // Insert into menu_items - using image_url instead of image_path
    $stmt = $conn->prepare("
        INSERT INTO menu_items 
        (item_name, category, short_description, full_description, base_price, prep_time, serving_size, image_path) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "ssssdiss",
        $data['itemName'],
        $data['category'],
        $shortDescription,
        $fullDescription,
        $data['basePrice'],
        $prepTime,
        $servingSize,
        $imageUrl  // Changed from $imagePath
    );
    
    $stmt->execute();
    $menuItemId = $stmt->insert_id;
    $stmt->close();

    // Insert availability (array of day names)
    if (!empty($data['availability']) && is_array($data['availability'])) {
        $availStmt = $conn->prepare("
            INSERT INTO menu_availability (menu_item_id, day_of_week, is_available) 
            VALUES (?, ?, 1)
        ");
        
        foreach ($data['availability'] as $day) {
            $availStmt->bind_param("is", $menuItemId, $day);
            $availStmt->execute();
        }
        $availStmt->close();
    }

    // Insert dietary info (array of dietary types)
    if (!empty($data['dietary']) && is_array($data['dietary'])) {
        $dietStmt = $conn->prepare("
            INSERT INTO menu_dietary (menu_item_id, dietary_type) 
            VALUES (?, ?)
        ");
        
        foreach ($data['dietary'] as $dietType) {
            $dietStmt->bind_param("is", $menuItemId, $dietType);
            $dietStmt->execute();
        }
        $dietStmt->close();
    }

    // Insert special features
    if (!empty($data['specialFeatures']) && is_array($data['specialFeatures'])) {
        $featureStmt = $conn->prepare("
            INSERT INTO special_features (menu_item_id, feature_text, display_order) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($data['specialFeatures'] as $index => $feature) {
            $featureStmt->bind_param("isi", $menuItemId, $feature, $index);
            $featureStmt->execute();
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
                foreach ($group['options'] as $oIndex => $option) {
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
        "message" => "Menu item saved successfully!",
        "menuItemId" => $menuItemId,
        "imageUrl" => $imageUrl  // Changed from imagePath
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log error for debugging
    error_log("Menu item save error: " . $e->getMessage());
    
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