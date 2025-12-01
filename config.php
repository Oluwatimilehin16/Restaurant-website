<?php
// config.php - Database configuration for localhost

// Database credentials
define('DB_HOST', 'localhost');      // Usually 'localhost'
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');               // Your MySQL password (empty by default for root)
define('DB_NAME', 'broiche_brew');  // Replace with your database name
define('DB_PORT', 3306);             // Default MySQL port

// Upload directory for images
define('UPLOAD_DIR', __DIR__ . '/uploads/menu_items/');

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Max file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed image types
define('ALLOWED_TYPES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp'
]);

// Function to get database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
