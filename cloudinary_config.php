<?php
require_once __DIR__ . '/vendor/autoload.php';

use Cloudinary\Cloudinary;

function cloudinary() {
    static $cloud;
    if ($cloud) return $cloud;

    // Get credentials from environment variables
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey    = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');

    // Validate credentials
    if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
        error_log("Cloudinary credentials missing!");
        error_log("Cloud Name: " . ($cloudName ? "SET" : "NOT SET"));
        error_log("API Key: " . ($apiKey ? "SET" : "NOT SET"));
        error_log("API Secret: " . ($apiSecret ? "SET" : "NOT SET"));
        throw new Exception("Cloudinary credentials not configured");
    }

    try {
        $cloud = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName
            ],
            'api' => [
                'key' => $apiKey,
                'secret' => $apiSecret
            ],
            'url' => [
                'secure' => true
            ]
        ]);

        error_log("Cloudinary initialized successfully");
        return $cloud;
        
    } catch (Exception $e) {
        error_log("Cloudinary initialization failed: " . $e->getMessage());
        throw $e;
    }
}