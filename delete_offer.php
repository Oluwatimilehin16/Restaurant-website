<?php
// delete_offer.php - Delete a special offer

require_once 'config.php';

header('Content-Type: application/json');

try {
    // Read JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || empty($data['id'])) {
        throw new Exception("Offer ID is required");
    }

    $conn = getDBConnection();
    $conn->begin_transaction();
    
    $offerId = intval($data['id']);
    
    // Get the image path before deleting
    $stmt = $conn->prepare("SELECT image_path FROM special_offers WHERE id = ?");
    $stmt->bind_param("i", $offerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $offer = $result->fetch_assoc();
    $stmt->close();
    
    if (!$offer) {
        throw new Exception("Offer not found");
    }
    
    // Delete the offer from database
    $stmt = $conn->prepare("DELETE FROM special_offers WHERE id = ?");
    $stmt->bind_param("i", $offerId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Delete the image file if it exists
        if (file_exists($offer['image_path'])) {
            @unlink($offer['image_path']);
        }
        
        $success = true;
        $message = "Offer deleted successfully!";
    } else {
        throw new Exception("Failed to delete offer");
    }
    
    $stmt->close();
    $conn->commit();
    $conn->close();

    echo json_encode([
        "success" => $success,
        "message" => $message
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
        $conn->close();
    }
    
    error_log("Delete offer error: " . $e->getMessage());
    
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>