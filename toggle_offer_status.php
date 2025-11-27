<?php
// toggle_offer_status.php - Activate or deactivate a special offer

header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = getDBConnection();
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (empty($data['id']) || !isset($data['isActive'])) {
        throw new Exception('Missing required fields: id and isActive');
    }
    
    $offerId = intval($data['id']);
    $isActive = $data['isActive'] ? 1 : 0;
    
    // First check if offer exists
    $checkStmt = $conn->prepare("SELECT id, is_active FROM special_offers WHERE id = ?");
    $checkStmt->bind_param("i", $offerId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Offer not found');
    }
    
    $currentOffer = $result->fetch_assoc();
    $checkStmt->close();
    
    // Update the status
    $query = "UPDATE special_offers SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $isActive, $offerId);
    $stmt->execute();
    
    $message = $isActive ? "Offer activated successfully!" : "Offer deactivated successfully!";
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>