<?php 
require_once 'config.php';

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

    // Validate required fields
    if (empty($data['itemName'])) throw new Exception("Item name is required");
    if (empty($data['category'])) throw new Exception("Category is required");
    if (empty($data['basePrice']) || $data['basePrice'] <= 0) throw new Exception("Valid base price is required");
    if (empty($data['imageData'])) throw new Exception("Image is required");

    // Handle image upload to local server
    $imageData = $data['imageData'];
    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
        $imageType = strtolower($matches[1]);
        $imageBase64 = $matches[2];

        // Validate type
        $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        if (!in_array($imageType, $allowedTypes)) throw new Exception("Invalid image type");

        // Decode
        $imageContent = base64_decode($imageBase64);
        if ($imageContent === false) throw new Exception("Failed to decode image");

        // Check size
        if (strlen($imageContent) > MAX_FILE_SIZE) throw new Exception("Image too large");

        // Generate unique filename
        $filename = time() . '_' . uniqid() . '.' . $imageType;
        $filepath = UPLOAD_DIR . $filename;

        // Save file
        if (!file_put_contents($filepath, $imageContent)) {
            throw new Exception("Failed to save image on server");
        }
    } else {
        throw new Exception("Invalid image format");
    }

    // Now save to database
    $conn = getDBConnection();
    $conn->begin_transaction();

    $shortDescription = !empty($data['shortDescription']) ? $data['shortDescription'] : null;
    $fullDescription  = !empty($data['fullDescription']) ? $data['fullDescription'] : null;
    $prepTime         = !empty($data['prepTime']) ? intval($data['prepTime']) : null;
    $servingSize      = !empty($data['servingSize']) ? $data['servingSize'] : null;

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
        $filepath  // local path
    );

    $stmt->execute();
    $menuItemId = $stmt->insert_id;
    $stmt->close();

    // Optionally insert availability, dietary info, special features, and customizations here (same as before)

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Menu item saved successfully!",
        "menuItemId" => $menuItemId,
        "imagePath" => $filepath
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) $conn->rollback();

    error_log("Menu item save error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn->ping()) $conn->close();
}
?>
