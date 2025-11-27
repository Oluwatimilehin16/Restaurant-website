<?php
// save_offer.php - Save or update a special offer

require_once 'config.php';

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

    // Handle image upload if new image is provided
    $imagePath = null;
    if (!empty($data['imageData'])) {
        $imageData = $data['imageData'];
        
        // Extract base64 data and image type
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
            $imageType = strtolower($matches[1]);
            $imageBase64 = $matches[2];
            
            // Validate image type
            $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
            if (!in_array($imageType, $allowedTypes)) {
                throw new Exception("Invalid image type: $imageType");
            }
            
            // Decode base64
            $imageContent = base64_decode($imageBase64);
            if ($imageContent === false) {
                throw new Exception("Failed to decode image data");
            }
            
            // Check file size (limit to 5MB)
            $maxSize = 5 * 1024 * 1024;
            if (strlen($imageContent) > $maxSize) {
                throw new Exception("Image file too large. Maximum size is 5MB");
            }
            
            // Generate filename and save
            $fileName = time() . "_offer." . $imageType;
            
            // Ensure upload directory exists
            $uploadDir = 'uploads/offers/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            $destPath = $uploadDir . $fileName;
            
            if (!file_put_contents($destPath, $imageContent)) {
                throw new Exception("Failed to save image file");
            }
            
            $imagePath = $uploadDir . $fileName;
        } else {
            throw new Exception("Invalid image data format");
        }
    }

    // Validate required fields
    if (empty($data['title'])) {
        throw new Exception("Title is required");
    }
    if (empty($data['originalPrice']) || $data['originalPrice'] <= 0) {
        throw new Exception("Valid original price is required");
    }
    if (empty($data['discountedPrice']) || $data['discountedPrice'] <= 0) {
        throw new Exception("Valid discounted price is required");
    }
    if (empty($data['validFrom']) || empty($data['validUntil'])) {
        throw new Exception("Valid dates are required");
    }

    // Check if updating existing offer
    if (!empty($data['id'])) {
        // UPDATE existing offer
        $offerId = intval($data['id']);
        
        if ($imagePath) {
            // Get old image path to delete it
            $stmt = $conn->prepare("SELECT image_path FROM special_offers WHERE id = ?");
            $stmt->bind_param("i", $offerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $oldOffer = $result->fetch_assoc();
            $stmt->close();
            
            // Delete old image file if it exists
            if ($oldOffer && file_exists($oldOffer['image_path'])) {
                @unlink($oldOffer['image_path']);
            }
            
            // Update with new image
            $stmt = $conn->prepare("
                UPDATE special_offers 
                SET title = ?, 
                    description = ?, 
                    image_path = ?, 
                    original_price = ?, 
                    discounted_price = ?, 
                    discount_percentage = ?, 
                    valid_from = ?, 
                    valid_until = ?, 
                    badge = ?, 
                    display_order = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param(
                "sssddisssii",
                $data['title'],
                $data['description'],
                $imagePath,
                $data['originalPrice'],
                $data['discountedPrice'],
                $data['discountPercentage'],
                $data['validFrom'],
                $data['validUntil'],
                $data['badge'],
                $data['displayOrder'],
                $offerId
            );
        } else {
            // Update without changing image
            $stmt = $conn->prepare("
                UPDATE special_offers 
                SET title = ?, 
                    description = ?, 
                    original_price = ?, 
                    discounted_price = ?, 
                    discount_percentage = ?, 
                    valid_from = ?, 
                    valid_until = ?, 
                    badge = ?, 
                    display_order = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param(
                "ssddisssii",
                $data['title'],
                $data['description'],
                $data['originalPrice'],
                $data['discountedPrice'],
                $data['discountPercentage'],
                $data['validFrom'],
                $data['validUntil'],
                $data['badge'],
                $data['displayOrder'],
                $offerId
            );
        }
        
        $stmt->execute();
        $stmt->close();
        
        $message = "Offer updated successfully!";
    } else {
        // INSERT new offer
        if (!$imagePath) {
            throw new Exception("Image is required for new offers");
        }
        
        $stmt = $conn->prepare("
            INSERT INTO special_offers 
            (title, description, image_path, original_price, discounted_price, discount_percentage, valid_from, valid_until, badge, display_order) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sssddssssi",
            $data['title'],
            $data['description'],
            $imagePath,
            $data['originalPrice'],
            $data['discountedPrice'],
            $data['discountPercentage'],
            $data['validFrom'],
            $data['validUntil'],
            $data['badge'],
            $data['displayOrder']
        );
        
        $stmt->execute();
        $offerId = $stmt->insert_id;
        $stmt->close();
        
        $message = "Offer created successfully!";
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => $message,
        "offerId" => $offerId,
        "imagePath" => $imagePath
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log error for debugging
    error_log("Offer save error: " . $e->getMessage());
    
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
