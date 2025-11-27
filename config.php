<?php
// config.php - Database configuration using ONLY environment variables

// Database credentials from environment
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_PORT', getenv('DB_PORT'));

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // Check connection
    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }

    // Set charset
    $conn->set_charset("utf8mb4");

    return $conn;
}

// Upload directory for images
define('UPLOAD_DIR', 'uploads/menu_items/');

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Allowed image types
define('ALLOWED_TYPES', [
    'image/jpeg', 'image/jpg', 'image/png',
    'image/gif', 'image/webp'
]);

// Max file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
?>
